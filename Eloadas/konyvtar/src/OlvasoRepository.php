<?php
declare(strict_types=1);

final class OlvasoRepository
{
    public function __construct(private PDO $db)
    {
    }

    // Minden olvasó, a nyitott és a lejárt kölcsönzéseinek számával.
    public function osszes(string $ma): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.id, o.nev, o.olvasojegy,
                    COUNT(ko.id)                               AS nyitott,
                    COALESCE(SUM(ko.hatarido < ?), 0)          AS lejart
               FROM olvaso o
               LEFT JOIN kolcsonzes ko ON ko.olvaso_id = o.id AND ko.visszahozva IS NULL
              GROUP BY o.id
              ORDER BY o.nev'
        );
        $stmt->execute([$ma]);
        return $stmt->fetchAll();
    }

    public function egy(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nev, olvasojegy, email FROM olvaso WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
