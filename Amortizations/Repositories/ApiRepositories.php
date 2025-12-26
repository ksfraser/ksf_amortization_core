<?php
namespace Ksfraser\Amortizations\Repositories;

use Ksfraser\Amortizations\Models\Loan;

/**
 * LoanRepository: Interface for loan data access
 */
interface LoanRepositoryInterface
{
    /**
     * Find loan by ID
     */
    public function findById(int $id): ?Loan;

    /**
     * Get all loans with pagination
     */
    public function list(int $offset = 0, int $limit = 20): array;

    /**
     * Count total loans
     */
    public function count(): int;

    /**
     * Insert new loan
     */
    public function insert(Loan $loan): int;

    /**
     * Update existing loan
     */
    public function update(Loan $loan): bool;

    /**
     * Delete loan
     */
    public function delete(int $loanId): bool;
}

/**
 * ScheduleRepository: Interface for schedule data access
 */
interface ScheduleRepositoryInterface
{
    /**
     * Get schedule rows for a loan
     */
    public function getScheduleForLoan(int $loanId, int $offset = 0, int $limit = 100): array;

    /**
     * Count schedule rows for loan
     */
    public function countScheduleRows(int $loanId): int;

    /**
     * Insert schedule row
     */
    public function insertRow(array $row): int;

    /**
     * Update schedule row
     */
    public function updateRow(int $rowId, array $data): bool;

    /**
     * Delete schedule rows after date
     */
    public function deleteScheduleAfterDate(int $loanId, string $afterDate): int;

    /**
     * Get single schedule row
     */
    public function getRow(int $rowId): ?array;
}

/**
 * EventRepository: Interface for loan event data access
 */
interface EventRepositoryInterface
{
    /**
     * Get events for a loan
     */
    public function getEventsForLoan(int $loanId, int $offset = 0, int $limit = 50): array;

    /**
     * Count events for loan
     */
    public function countEventsForLoan(int $loanId): int;

    /**
     * Find event by ID
     */
    public function findById(int $eventId): ?array;

    /**
     * Create new event
     */
    public function createEvent(
        int $loanId,
        string $eventType,
        string $eventDate,
        float $amount = 0,
        string $notes = ''
    ): int;

    /**
     * Delete event
     */
    public function delete(int $eventId): bool;

    /**
     * Get events for loan up to date
     */
    public function getEventsUpToDate(int $loanId, string $upToDate): array;
}

/**
 * BaseLoanRepository: Base implementation with common functionality
 */
abstract class BaseLoanRepository implements LoanRepositoryInterface
{
    /**
     * Convert database row to Loan model
     */
    protected function rowToLoan(array $row): Loan
    {
        $loan = new Loan();
        $loan->setId($row['id'] ?? null)
             ->setPrincipal($row['principal'] ?? 0)
             ->setAnnualRate($row['interest_rate'] ?? 0)
             ->setTermMonths($row['term_months'] ?? 0)
             ->setStartDate($row['start_date'] ?? '')
             ->setLoanType($row['loan_type'] ?? 'other')
             ->setPaymentFrequency($row['payment_frequency'] ?? 'monthly')
             ->setDescription($row['description'] ?? '');

        return $loan;
    }

    /**
     * Convert Loan model to database row
     */
    protected function loanToRow(Loan $loan): array
    {
        return [
            'principal' => $loan->getPrincipal(),
            'interest_rate' => $loan->getAnnualRate(),
            'term_months' => $loan->getTermMonths(),
            'start_date' => $loan->getStartDate(),
            'loan_type' => $loan->getLoanType(),
            'payment_frequency' => $loan->getPaymentFrequency(),
            'description' => $loan->getDescription(),
        ];
    }
}

/**
 * MockLoanRepository: In-memory implementation for testing/demo
 */
class MockLoanRepository extends BaseLoanRepository
{
    private static array $loans = [];
    private static int $nextId = 1;

    public function findById(int $id): ?Loan
    {
        return isset(self::$loans[$id]) ? $this->rowToLoan(self::$loans[$id]) : null;
    }

    public function list(int $offset = 0, int $limit = 20): array
    {
        $items = array_slice(self::$loans, $offset, $limit);
        return array_map(fn($row) => $this->rowToLoan($row), $items);
    }

    public function count(): int
    {
        return count(self::$loans);
    }

    public function insert(Loan $loan): int
    {
        $id = self::$nextId++;
        $row = $this->loanToRow($loan);
        $row['id'] = $id;
        self::$loans[$id] = $row;
        return $id;
    }

    public function update(Loan $loan): bool
    {
        $id = $loan->getId();
        if (!isset(self::$loans[$id])) {
            return false;
        }
        $row = $this->loanToRow($loan);
        $row['id'] = $id;
        self::$loans[$id] = $row;
        return true;
    }

    public function delete(int $loanId): bool
    {
        unset(self::$loans[$loanId]);
        return true;
    }

    public static function reset(): void
    {
        self::$loans = [];
        self::$nextId = 1;
    }
}

/**
 * MockScheduleRepository: In-memory implementation for testing/demo
 */
class MockScheduleRepository implements ScheduleRepositoryInterface
{
    private static array $rows = [];
    private static int $nextId = 1;

    public function getScheduleForLoan(int $loanId, int $offset = 0, int $limit = 100): array
    {
        $loanRows = array_filter(
            self::$rows,
            fn($row) => $row['loan_id'] === $loanId
        );
        return array_slice($loanRows, $offset, $limit);
    }

    public function countScheduleRows(int $loanId): int
    {
        return count(array_filter(
            self::$rows,
            fn($row) => $row['loan_id'] === $loanId
        ));
    }

    public function insertRow(array $row): int
    {
        $id = self::$nextId++;
        $row['id'] = $id;
        self::$rows[$id] = $row;
        return $id;
    }

    public function updateRow(int $rowId, array $data): bool
    {
        if (!isset(self::$rows[$rowId])) {
            return false;
        }
        self::$rows[$rowId] = array_merge(self::$rows[$rowId], $data);
        return true;
    }

    public function deleteScheduleAfterDate(int $loanId, string $afterDate): int
    {
        $deleted = 0;
        foreach (self::$rows as $id => $row) {
            if ($row['loan_id'] === $loanId && $row['payment_date'] > $afterDate) {
                unset(self::$rows[$id]);
                $deleted++;
            }
        }
        return $deleted;
    }

    public function getRow(int $rowId): ?array
    {
        return self::$rows[$rowId] ?? null;
    }

    public static function reset(): void
    {
        self::$rows = [];
        self::$nextId = 1;
    }
}

/**
 * MockEventRepository: In-memory implementation for testing/demo
 */
class MockEventRepository implements EventRepositoryInterface
{
    private static array $events = [];
    private static int $nextId = 1;

    public function getEventsForLoan(int $loanId, int $offset = 0, int $limit = 50): array
    {
        $loanEvents = array_filter(
            self::$events,
            fn($event) => $event['loan_id'] === $loanId
        );
        return array_slice($loanEvents, $offset, $limit);
    }

    public function countEventsForLoan(int $loanId): int
    {
        return count(array_filter(
            self::$events,
            fn($event) => $event['loan_id'] === $loanId
        ));
    }

    public function findById(int $eventId): ?array
    {
        return self::$events[$eventId] ?? null;
    }

    public function createEvent(
        int $loanId,
        string $eventType,
        string $eventDate,
        float $amount = 0,
        string $notes = ''
    ): int {
        $id = self::$nextId++;
        self::$events[$id] = [
            'id' => $id,
            'loan_id' => $loanId,
            'event_type' => $eventType,
            'event_date' => $eventDate,
            'amount' => $amount,
            'notes' => $notes,
            'created_at' => date('c'),
        ];
        return $id;
    }

    public function delete(int $eventId): bool
    {
        unset(self::$events[$eventId]);
        return true;
    }

    public function getEventsUpToDate(int $loanId, string $upToDate): array
    {
        return array_filter(
            self::$events,
            fn($event) => $event['loan_id'] === $loanId && $event['event_date'] <= $upToDate
        );
    }

    public static function reset(): void
    {
        self::$events = [];
        self::$nextId = 1;
    }
}

/**
 * LoanRepository: Adapter for generic DataProvider
 */
class LoanRepository extends BaseLoanRepository
{
    private $dataProvider;

    public function __construct($dataProvider = null)
    {
        $this->dataProvider = $dataProvider;
    }

    public function findById(int $id): ?Loan
    {
        // Delegated to mock for now - will be overridden in platform-specific adaptors
        return (new MockLoanRepository())->findById($id);
    }

    public function list(int $offset = 0, int $limit = 20): array
    {
        return (new MockLoanRepository())->list($offset, $limit);
    }

    public function count(): int
    {
        return (new MockLoanRepository())->count();
    }

    public function insert(Loan $loan): int
    {
        return (new MockLoanRepository())->insert($loan);
    }

    public function update(Loan $loan): bool
    {
        return (new MockLoanRepository())->update($loan);
    }

    public function delete(int $loanId): bool
    {
        return (new MockLoanRepository())->delete($loanId);
    }
}

/**
 * ScheduleRepository: Generic schedule data access
 */
class ScheduleRepository implements ScheduleRepositoryInterface
{
    private $dataProvider;

    public function __construct($dataProvider = null)
    {
        $this->dataProvider = $dataProvider;
    }

    public function getScheduleForLoan(int $loanId, int $offset = 0, int $limit = 100): array
    {
        return (new MockScheduleRepository())->getScheduleForLoan($loanId, $offset, $limit);
    }

    public function countScheduleRows(int $loanId): int
    {
        return (new MockScheduleRepository())->countScheduleRows($loanId);
    }

    public function insertRow(array $row): int
    {
        return (new MockScheduleRepository())->insertRow($row);
    }

    public function updateRow(int $rowId, array $data): bool
    {
        return (new MockScheduleRepository())->updateRow($rowId, $data);
    }

    public function deleteScheduleAfterDate(int $loanId, string $afterDate): int
    {
        return (new MockScheduleRepository())->deleteScheduleAfterDate($loanId, $afterDate);
    }

    public function getRow(int $rowId): ?array
    {
        return (new MockScheduleRepository())->getRow($rowId);
    }
}

/**
 * EventRepository: Generic event data access
 */
class EventRepository implements EventRepositoryInterface
{
    private $dataProvider;

    public function __construct($dataProvider = null)
    {
        $this->dataProvider = $dataProvider;
    }

    public function getEventsForLoan(int $loanId, int $offset = 0, int $limit = 50): array
    {
        return (new MockEventRepository())->getEventsForLoan($loanId, $offset, $limit);
    }

    public function countEventsForLoan(int $loanId): int
    {
        return (new MockEventRepository())->countEventsForLoan($loanId);
    }

    public function findById(int $eventId): ?array
    {
        return (new MockEventRepository())->findById($eventId);
    }

    public function createEvent(
        int $loanId,
        string $eventType,
        string $eventDate,
        float $amount = 0,
        string $notes = ''
    ): int {
        return (new MockEventRepository())->createEvent($loanId, $eventType, $eventDate, $amount, $notes);
    }

    public function delete(int $eventId): bool
    {
        return (new MockEventRepository())->delete($eventId);
    }

    public function getEventsUpToDate(int $loanId, string $upToDate): array
    {
        return (new MockEventRepository())->getEventsUpToDate($loanId, $upToDate);
    }
}
