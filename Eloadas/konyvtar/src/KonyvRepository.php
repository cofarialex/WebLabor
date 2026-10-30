<?php
declare(strict_types=1);

final class KonyvRepository
{
    public function __construct(private PDO $db)
    {
    }

    // Keresés címben, szerzőben és ISBN-ben, lapozással.
    // Minden könyvnél visszaadja, hány példánya van és abból hány elérhető.
    public function keres(string $kifejezes, int $limit, int $offset): array
    {
        $minta = self::likeMinta($kifejezes);
        $stmt = $this->db->prepare(
            "SELECT k.id, k.cim, k.szerzo, k.isbn,
                    COUNT(p.id)                AS osszes,
                    COUNT(p.id) - COUNT(ko.id) AS elerheto
               FROM konyv k
               LEFT JOIN peldany p     ON p.konyv_id = k.id
               LEFT JOIN kolcsonzes ko ON ko.peldany_id = p.id AND ko.visszahozva IS NULL
              WHERE k.cim LIKE ? ESCAPE '\\' OR k.szerzo LIKE ? ESCAPE '\\' OR k.isbn LIKE ? ESCAPE '\\'
              GROUP BY k.id
              ORDER BY k.cim
              LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $minta);
        $stmt->bindValue(2, $minta);
        $stmt->bindValue(3, $minta);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function talalatokSzama(string $kifejezes): int
    {
        $minta = self::likeMinta($kifejezes);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM konyv
              WHERE cim LIKE ? ESCAPE '\\' OR szerzo LIKE ? ESCAPE '\\' OR isbn LIKE ? ESCAPE '\\'"
        );
        $stmt->execute([$minta, $minta, $minta]);
        return (int) $stmt->fetchColumn();
    }

    public function egy(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, cim, szerzo, isbn FROM konyv WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // A könyv példányai az esetleges nyitott kölcsönzéssel együtt
    // (ha a példány bent van, a kölcsönzés oszlopai NULL-ok).
    public function peldanyok(int $konyvId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.leltari_szam,
                    ko.hatarido, o.id AS olvaso_id, o.nev AS olvaso_nev
               FROM peldany p
               LEFT JOIN kolcsonzes ko ON ko.peldany_id = p.id AND ko.visszahozva IS NULL
               LEFT JOIN olvaso o      ON o.id = ko.olvaso_id
              WHERE p.konyv_id = ?
              ORDER BY p.leltari_szam'
        );
        $stmt->execute([$konyvId]);
        return $stmt->fetchAll();
    }

    // A felhasználó által beírt % és _ a LIKE-ban helyettesítő karakter lenne,
    // ezért escape-eljük őket – így a keresőszó mindig szó szerint értendő.
    private static function likeMinta(string $s): string
    {
        return '%' . strtr($s, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    }
}
