<?php
namespace Ksfraser\Amortizations;

/**
 * Interface for platform-specific loan event data providers
 */
interface LoanEventProviderInterface {
    public function insertLoanEvent(LoanEvent $event): void;
    public function getLoanEvents(int $loan_id): array; // returns array of LoanEvent
    public function updateLoanEvent(LoanEvent $event): void;
    public function deleteLoanEvent(int $event_id): void;
}
