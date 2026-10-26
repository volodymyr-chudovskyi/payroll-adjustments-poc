<?php

declare(strict_types=1);

namespace Payroll\Infrastructure;

use PDO;
use PDOException;
use Payroll\Domain\EarningLine;
use Payroll\Domain\EarningLineRepository;
use Payroll\Domain\Exception\EarningLineNotFound;
use Payroll\Domain\ValueObject\LineId;

final class SqliteEventStoreRepository implements EarningLineRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EventSerializer $serializer = new JsonEventSerializer(),
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS earning_line_events (
                aggregate_id TEXT NOT NULL,
                version INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                PRIMARY KEY (aggregate_id, version)
            )
            SQL);
    }

    public function save(EarningLine $line): void
    {
        $events = $line->releaseEvents();

        if ($events === []) {
            return;
        }

        $version = $line->version() - count($events);
        $insert = $this->pdo->prepare(
            'INSERT INTO earning_line_events (aggregate_id, version, event_type, payload) VALUES (?, ?, ?, ?)',
        );

        $this->pdo->beginTransaction();

        try {
            foreach ($events as $event) {
                ['type' => $type, 'payload' => $payload] = $this->serializer->serialize($event);
                $insert->execute([$line->id()->value, ++$version, $type, $payload]);
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();

            if ((string) $exception->getCode() === '23000') {
                throw new ConcurrencyConflict($line->id());
            }

            throw $exception;
        }
    }

    public function get(LineId $id): EarningLine
    {
        $select = $this->pdo->prepare(
            'SELECT event_type, payload FROM earning_line_events WHERE aggregate_id = ? ORDER BY version',
        );
        $select->execute([$id->value]);

        /** @var list<array{event_type: string, payload: string}> $rows */
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            throw new EarningLineNotFound($id);
        }

        return EarningLine::reconstitute($id, array_map(
            fn(array $row) => $this->serializer->deserialize($row['event_type'], $row['payload']),
            $rows,
        ));
    }
}
