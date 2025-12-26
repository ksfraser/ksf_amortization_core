<?php
namespace Ksfraser\Amortizations\FA;

use Ksfraser\Amortizations\LoanEvent;
use Ksfraser\Amortizations\LoanEventProviderInterface;
use Ksfraser\Amortizations\GenericLoanEventProvider;

class FALoanEventProvider implements LoanEventProviderInterface {
    private $genericProvider;
    public function __construct($pdo, $dbPrefix = '') {
        $this->genericProvider = new GenericLoanEventProvider($pdo, $dbPrefix);
    }
    public function insertLoanEvent(LoanEvent $event): void {
        $this->genericProvider->insertLoanEvent($event);
    }
    public function getLoanEvents(int $loan_id): array {
        return $this->genericProvider->getLoanEvents($loan_id);
    }
    public function updateLoanEvent(LoanEvent $event): void {
        $this->genericProvider->updateLoanEvent($event);
    }
    public function deleteLoanEvent(int $event_id): void {
        $this->genericProvider->deleteLoanEvent($event_id);
    }
}
