<?php
require_once '../db.php';
require_once '../level_system.php';

class ArenaManager {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    public function findMatch() {
        // Get current user's rating
        $stmt = $this->conn->prepare("
            SELECT arena_rating, level 
            FROM users 
            WHERE id = ?
        ");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Find opponent within rating range
        $min_rating = $user['arena_rating'] - 100;
        $max_rating = $user['arena_rating'] + 100;
        $min_level = max(1, $user['level'] - 3);
        $max_level = $user['level'] + 3;
        
        $stmt = $this->conn->prepare("
            SELECT id, username, arena_rating, level
            FROM users 
            WHERE id != ? 
            AND arena_rating BETWEEN ? AND ?
            AND level BETWEEN ? AND ?
            AND last_arena_match < NOW() - INTERVAL 5 MINUTE
            ORDER BY RAND()
            LIMIT 1
        ");
        
        $stmt->bind_param("siiii", $this->user_id, $min_rating, $max_rating, $min_level, $max_level);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    public function startMatch($opponent_id) {
        $this->conn->begin_transaction();
        
        try {
            // Create match record
            $stmt = $this->conn->prepare("
                INSERT INTO arena_matches (
                    season_id,
                    player1_id,
                    player2_id,
                    player1_rating,
                    player2_rating,
                    status
                ) VALUES (
                    (SELECT id FROM arena_seasons WHERE status = 'active' LIMIT 1),
                    ?, ?, 
                    (SELECT arena_rating FROM users WHERE id = ?),
                    (SELECT arena_rating FROM users WHERE id = ?),
                    'in_progress'
                )
            ");
            
            $stmt->bind_param("ssss", $this->user_id, $opponent_id, $this->user_id, $opponent_id);
            $stmt->execute();
            
            $match_id = $this->conn->insert_id;
            
            // Update last match time
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET last_arena_match = NOW()
                WHERE id IN (?, ?)
            ");
            
            $stmt->bind_param("ss", $this->user_id, $opponent_id);
            $stmt->execute();
            
            $this->conn->commit();
            return $match_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function processMatchResult($match_id, $winner_id) {
        $this->conn->begin_transaction();
        
        try {
            // Get match details
            $stmt = $this->conn->prepare("
                SELECT * FROM arena_matches WHERE id = ?
            ");
            $stmt->bind_param("i", $match_id);
            $stmt->execute();
            $match = $stmt->get_result()->fetch_assoc();
            
            // Calculate rating changes
            $rating_change = $this->calculateRatingChange(
                $match['player1_rating'],
                $match['player2_rating'],
                $winner_id == $match['player1_id']
            );
            
            // Update match record
            $stmt = $this->conn->prepare("
                UPDATE arena_matches 
                SET winner_id = ?,
                    rating_change = ?,
                    status = 'completed'
                WHERE id = ?
            ");
            
            $stmt->bind_param("sii", $winner_id, $rating_change, $match_id);
            $stmt->execute();
            
            // Update player ratings
            $this->updatePlayerRatings(
                $match['player1_id'],
                $match['player2_id'],
                $winner_id,
                $rating_change
            );
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function calculateRatingChange($rating1, $rating2, $player1_won) {
        $k = 32; // Rating change factor
        $expected = 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
        $actual = $player1_won ? 1 : 0;
        return round($k * ($actual - $expected));
    }
    
    private function updatePlayerRatings($player1_id, $player2_id, $winner_id, $rating_change) {
        // Update winner
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET arena_rating = arena_rating + ?,
                arena_wins = arena_wins + 1
            WHERE id = ?
        ");
        $stmt->bind_param("is", $rating_change, $winner_id);
        $stmt->execute();
        
        // Update loser
        $loser_id = $winner_id == $player1_id ? $player2_id : $player1_id;
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET arena_rating = arena_rating - ?,
                arena_losses = arena_losses + 1
            WHERE id = ?
        ");
        $stmt->bind_param("is", $rating_change, $loser_id);
        $stmt->execute();
    }
}