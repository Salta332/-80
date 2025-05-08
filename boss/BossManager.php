<?php
require_once '../db.php';
require_once '../level_system.php';

class BossManager {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    public function getActiveBosses() {
        $stmt = $this->conn->prepare("
            SELECT * FROM bosses 
            WHERE spawn_time <= NOW() 
            AND despawn_time > NOW()
            ORDER BY level ASC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function joinBossBattle($boss_id) {
        $this->conn->begin_transaction();
        
        try {
            // Get boss data
            $stmt = $this->conn->prepare("
                SELECT * FROM bosses WHERE id = ?
            ");
            $stmt->bind_param("s", $boss_id);
            $stmt->execute();
            $boss = $stmt->get_result()->fetch_assoc();
            
            if (!$boss) {
                throw new Exception("Boss not found");
            }
            
            // Check if player meets level requirement
            $stmt = $this->conn->prepare("
                SELECT level FROM users WHERE id = ?
            ");
            $stmt->bind_param("s", $this->user_id);
            $stmt->execute();
            $player = $stmt->get_result()->fetch_assoc();
            
            if ($player['level'] < $boss['min_level']) {
                throw new Exception("Player level too low");
            }
            
            // Get or create active battle
            $stmt = $this->conn->prepare("
                SELECT * FROM boss_battles 
                WHERE boss_id = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->bind_param("s", $boss_id);
            $stmt->execute();
            $battle = $stmt->get_result()->fetch_assoc();
            
            if (!$battle) {
                // Create new battle
                $stmt = $this->conn->prepare("
                    INSERT INTO boss_battles (boss_id, current_health, status)
                    VALUES (?, ?, 'active')
                ");
                $stmt->bind_param("si", $boss_id, $boss['max_health']);
                $stmt->execute();
                $battle_id = $this->conn->insert_id;
            } else {
                $battle_id = $battle['id'];
            }
            
            // Add player to battle
            $stmt = $this->conn->prepare("
                INSERT INTO boss_battle_participants (battle_id, user_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ");
            $stmt->bind_param("ss", $battle_id, $this->user_id);
            $stmt->execute();
            
            $this->conn->commit();
            return $battle_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function attackBoss($battle_id, $damage) {
        $this->conn->begin_transaction();
        
        try {
            // Update boss health
            $stmt = $this->conn->prepare("
                UPDATE boss_battles 
                SET current_health = GREATEST(0, current_health - ?)
                WHERE id = ? AND status = 'active'
                RETURNING current_health
            ");
            $stmt->bind_param("is", $damage, $battle_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            // Update player damage
            $stmt = $this->conn->prepare("
                UPDATE boss_battle_participants
                SET damage_dealt = damage_dealt + ?
                WHERE battle_id = ? AND user_id = ?
            ");
            $stmt->bind_param("iss", $damage, $battle_id, $this->user_id);
            $stmt->execute();
            
            // Check if boss defeated
            if ($result['current_health'] <= 0) {
                $this->completeBossBattle($battle_id);
            }
            
            $this->conn->commit();
            return $result['current_health'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function completeBossBattle($battle_id) {
        // Update battle status
        $stmt = $this->conn->prepare("
            UPDATE boss_battles 
            SET status = 'completed',
                end_time = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("s", $battle_id);
        $stmt->execute();
        
        // Get battle participants
        $stmt = $this->conn->prepare("
            SELECT user_id, damage_dealt
            FROM boss_battle_participants
            WHERE battle_id = ?
            ORDER BY damage_dealt DESC
        ");
        $stmt->bind_param("s", $battle_id);
        $stmt->execute();
        $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Distribute rewards based on damage contribution
        foreach ($participants as $participant) {
            $this->distributeRewards($battle_id, $participant['user_id'], $participant['damage_dealt']);
        }
    }
    
    private function distributeRewards($battle_id, $user_id, $damage_dealt) {
        // Get boss rewards
        $stmt = $this->conn->prepare("
            SELECT b.rewards
            FROM boss_battles bb
            JOIN bosses b ON bb.boss_id = b.id
            WHERE bb.id = ?
        ");
        $stmt->bind_param("s", $battle_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $rewards = json_decode($result['rewards'], true);
        
        // Calculate reward multiplier based on damage contribution
        $stmt = $this->conn->prepare("
            SELECT SUM(damage_dealt) as total_damage
            FROM boss_battle_participants
            WHERE battle_id = ?
        ");
        $stmt->bind_param("s", $battle_id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc();
        $contribution = $damage_dealt / $total['total_damage'];
        
        // Apply rewards
        $stmt = $this->conn->prepare("
            UPDATE users
            SET exp = exp + ?,
                gold = gold + ?,
                crystals = crystals + ?
            WHERE id = ?
        ");
        
        $exp_reward = floor($rewards['exp'] * $contribution);
        $gold_reward = floor($rewards['gold'] * $contribution);
        $crystal_reward = floor($rewards['crystals'] * $contribution);
        
        $stmt->bind_param("iiis", $exp_reward, $gold_reward, $crystal_reward, $user_id);
        $stmt->execute();
        
        // Mark rewards as claimed
        $stmt = $this->conn->prepare("
            UPDATE boss_battle_participants
            SET rewards_claimed = true
            WHERE battle_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ss", $battle_id, $user_id);
        $stmt->execute();
    }
}