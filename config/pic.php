<?php

declare(strict_types=1);

function getPicOptions(PDO $pdo): array
{
    $statement = $pdo->query('SELECT id, name FROM pics ORDER BY name ASC');

    $options = $statement->fetchAll();

    if (isTajProject()) {
        $options = array_values(array_filter(
            $options,
            static fn (array $row): bool => in_array(
                (string) ($row['name'] ?? ''),
                ['DISHUB', 'JLI'],
                true
            )
        ));
    }

    if (userCanAccessValue('*')) {
        return $options;
    }

    return array_values(array_filter(
        $options,
        static fn (array $row): bool => userCanAccessValue((string) ($row['name'] ?? ''))
    ));
}

function getPicNames(PDO $pdo): array
{
    return array_map(
        static fn (array $row): string => (string) $row['name'],
        getPicOptions($pdo)
    );
}

function isValidPicName(PDO $pdo, string $name): bool
{
    if ($name === '') {
        return false;
    }

    $statement = $pdo->prepare('SELECT COUNT(*) FROM pics WHERE name = :name');
    $statement->execute([':name' => $name]);

    return (int) $statement->fetchColumn() > 0;
}
