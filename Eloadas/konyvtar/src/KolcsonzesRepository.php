<?php
declare(strict_types=1);

// A kölcsönzések minden SQL-je: kiadás, visszavétel, listák.
final class KolcsonzesRepository
{
    public const KOLCSONZESI_IDO_NAP = 14;

    // A listák közös része: kölcsönzés + példány + könyv + olvasó (JOIN 4 táblán).
    private const LISTA_SQL =
        'SELECT ko.id, ko.kiadva, ko.hatarido, ko.visszahozva,
                p.leltari_szam, k.id AS konyv_id, k.cim,
                o.id AS olvaso_id, o.nev AS olvaso_nev
           FROM kolcsonzes ko
           JOIN peldany p ON p.id = ko.peldany_id
           JOIN konyv k   ON k.id = p.konyv_id
           JOIN olvaso o  ON o.id = ko.olvaso_id';

    public function __construct(private PDO $db)
    {
    }

    // Példány kiadása egy olvasónak. Határidő = kiadás napja + 14 nap.
    //
    // Versenyhelyzet ellen (Műhely: php11, antipattern: „Ellenőrzés, majd írás”):
    //  1. az ellenőrzés és a beszúrás EGY tranzakcióban fut, BEGIN IMMEDIATE-tel
    //     (az SQLite rögtön lefoglalja az írást, a másik kérés megvárja);
    //  2. ha valahogy mégis két kiadás jutna el az INSERT-ig, a schema.sql-ben lévő
    //     egy_nyitott_kolcsonzes egyedi index a másodikat elutasítja.
    public function kiad(string $leltariSzam, int $olvasoId, string $ma): array
    {
        $hatarido = (new DateTimeImmutable($ma))
            ->modify('+' . self::KOLCSONZESI_IDO_NAP . ' days')
            ->format('Y-m-d');

        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $this->db->prepare(
                'SELECT p.id, k.cim, ko.hatarido
                   FROM peldany p
                   JOIN konyv k ON k.id = p.konyv_id
                   LEFT JOIN kolcsonzes ko ON ko.peldany_id = p.id AND ko.visszahozva IS NULL
                  WHERE p.leltari_szam = ?'
            );
            $stmt->execute([$leltariSzam]);
            $peldany = $stmt->fetch();

            if (!$peldany) {
                throw new KolcsonzesHiba("Nincs „{$leltariSzam}” leltári számú példány.");
            }
            if ($peldany['hatarido'] !== null) {
                throw new KolcsonzesHiba('Ez a példány már ki van adva (határidő: ' . $peldany['hatarido'] . ').');
            }

            $stmt = $this->db->prepare('SELECT COUNT(*) FROM olvaso WHERE id = ?');
            $stmt->execute([$olvasoId]);
            if ((int) $stmt->fetchColumn() === 0) {
                throw new KolcsonzesHiba('Nincs ilyen olvasó.');
            }

            $this->db->prepare(
                'INSERT INTO kolcsonzes (peldany_id, olvaso_id, kiadva, hatarido) VALUES (?, ?, ?, ?)'
            )->execute([$peldany['id'], $olvasoId, $ma, $hatarido]);

            $this->db->exec('COMMIT');
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            // Az egyedi index sértése → érthető üzenet a nyers SQL-hiba helyett.
            if ($e instanceof PDOException && str_contains($e->getMessage(), 'UNIQUE')) {
                throw new KolcsonzesHiba('Ez a példány már ki van adva.');
            }
            throw $e;
        }

        return ['cim' => $peldany['cim'], 'hatarido' => $hatarido];
    }

    // Visszavétel: a visszahozás dátumát rögzítjük, a késést kiszámoljuk.
    // A WHERE ... visszahozva IS NULL miatt ugyanazt nem lehet kétszer visszavenni.
    public function visszavesz(int $kolcsonzesId, string $ma): array
    {
        $stmt = $this->db->prepare('UPDATE kolcsonzes SET visszahozva = ? WHERE id = ? AND visszahozva IS NULL');
        $stmt->execute([$ma, $kolcsonzesId]);
        if ($stmt->rowCount() === 0) {
            throw new KolcsonzesHiba('Ez a kölcsönzés nem létezik, vagy már visszahozták.');
        }

        $stmt = $this->db->prepare(self::LISTA_SQL . ' WHERE ko.id = ?');
        $stmt->execute([$kolcsonzesId]);
        $k = $stmt->fetch();
        $k['keses'] = self::kesesNapok($k['hatarido'], $ma);
        return $k;
    }

    // Minden nyitott (még vissza nem hozott) kölcsönzés, a legkorábbi határidő elöl.
    public function nyitottak(): array
    {
        return $this->db->query(self::LISTA_SQL . ' WHERE ko.visszahozva IS NULL ORDER BY ko.hatarido, ko.id')
                        ->fetchAll();
    }

    // Lejárt kölcsönzések: nyitott, és a határidő már elmúlt. Olvasó szerint rendezve,
    // hogy az oldal olvasónként csoportosítva tudja kiírni.
    public function lejartak(string $ma): array
    {
        $stmt = $this->db->prepare(
            self::LISTA_SQL . ' WHERE ko.visszahozva IS NULL AND ko.hatarido < ? ORDER BY o.nev, ko.hatarido'
        );
        $stmt->execute([$ma]);
        return $stmt->fetchAll();
    }

    // Egy olvasó összes kölcsönzése (a visszahozottak is), a legújabb elöl.
    public function elozmeny(int $olvasoId): array
    {
        $stmt = $this->db->prepare(self::LISTA_SQL . ' WHERE ko.olvaso_id = ? ORDER BY ko.kiadva DESC, ko.id DESC');
        $stmt->execute([$olvasoId]);
        return $stmt->fetchAll();
    }

    // Hány nappal késett: a határidő és a visszahozás (vagy, ha még nincs bent, a mai nap)
    // közötti napok száma. Határidőn belül 0.
    public static function kesesNapok(string $hatarido, string $meddig): int
    {
        $h = new DateTimeImmutable($hatarido);
        $m = new DateTimeImmutable($meddig);
        return $m > $h ? $h->diff($m)->days : 0;
    }
}
