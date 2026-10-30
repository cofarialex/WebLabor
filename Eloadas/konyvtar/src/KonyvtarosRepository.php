<?php
declare(strict_types=1);

final class KonyvtarosRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function felhasznalonevAlapjan(string $felhasznalonev): ?array
    {
        $stmt = $this->db->prepare('SELECT id, felhasznalonev, jelszo_hash, nev FROM konyvtaros WHERE felhasznalonev = ?');
        $stmt->execute([$felhasznalonev]);
        return $stmt->fetch() ?: null;
    }
}
