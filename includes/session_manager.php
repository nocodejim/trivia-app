<?php

class SessionManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getSession(string $sessionCode): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM game_sessions WHERE session_code = :session_code");
        $stmt->bindParam(':session_code', $sessionCode);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        return $session ?: null;
    }

    public function getPlayer(string $sessionCode, string $playerName): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE session_code = :session_code AND player_name = :player_name");
        $stmt->bindParam(':session_code', $sessionCode);
        $stmt->bindParam(':player_name', $playerName);
        $stmt->execute();
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        return $player ?: null;
    }

    public function isHost(string $sessionCode, string $hostEmail): bool {
        $session = $this->getSession($sessionCode);
        if (!$session) {
            return false;
        }
        return $session['host_email'] === $hostEmail;
    }

    public function getPlayers(string $sessionCode): array {
        $stmt = $this->pdo->prepare("SELECT player_name, score, current_answer FROM players WHERE session_code = :session_code ORDER BY score DESC");
        $stmt->bindParam(':session_code', $sessionCode);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
