# Amortization Module Requirements

## Overview
This module adds Amortization functionality to FrontAccounting, WordPress, or SuiteCRM. It follows MVC principles internally and is designed for multi-platform use.

## Features
- Menu entry under "Banking and General Ledger" called "Amortization" (FA only)
- MVC architecture: Model, View, Controller separation
- Amortization schedule management
- Integration with platform database and UI
- Unit tests for all code (PHPUnit)
- User Acceptance Testing (UAT)
- UML diagrams for architecture and models
- Platform adapters for FA, WordPress, SuiteCRM

## Loan Types
- Support amortization calculations for all loan types, with emphasis on Auto loans and Mortgages.
- Flexible architecture to add new loan types easily.
- Loan details include: amount financed, interest rate, payment frequency, interest calculation frequency, number of payments, regular payment amount (calculated, override allowed in admin), first payment date, last payment date.

## Repayment Schedules
- Allow selection of various repayment schedules (monthly, bi-weekly, weekly, custom).
- Support for different interest calculation methods (fixed, variable, etc.).
- Support for different interest calculation frequencies (monthly, bi-weekly, weekly, custom).

## Reporting
- Generate reports showing paydown: payment amount, principal portion, interest portion, remaining balance.
- Export and print options for reports.

## Staging Table
- Store calculated amortization schedules in a staging table.
- Each payment line can be reviewed before posting.

## GL Integration (FrontAccounting only)
- Button to transfer each payment line into the appropriate General Ledger accounts for the loan.
- Ensure proper mapping and validation before posting.
- Admin screen allows selection of Asset, Liability, Expense, and Asset Value GL accounts for loans/mortgages.
- "Add GL" buttons beside each GL selector for quick account creation.

## Admin Screens
- Admin screen for global module settings (interest rates, GL mappings, etc.).
- User admin screen for setting up loan details (amount, term, rate, schedule, etc.).
- Admin screen includes GL account selectors and "Add GL" buttons for Asset, Liability, Expense, and Asset Value accounts (FA only).
- Admin and user screens allow entry and display of amount financed, interest rate, payment frequency, interest calculation frequency, number of payments, regular payment amount (calculated, override allowed in admin), first payment date, and last payment date.

- All table names must be prefixed using a platform-specific DB_PREFIX constant/variable.
- FA: Use TB_PREF as DB_PREFIX.
- WordPress: Use $wpdb->prefix as DB_PREFIX.
- SuiteCRM: Define DB_PREFIX as needed for SuiteCRM conventions.
- All table names must be prefixed using a platform-specific DB_PREFIX constant/variable.
- FA: Use TB_PREF as DB_PREFIX.
- WordPress: Use $wpdb->prefix as DB_PREFIX.
- SuiteCRM: Define DB_PREFIX as needed for SuiteCRM conventions.
- Models and business logic are framework-agnostic and reusable, following PSR-4 autoloading via Composer.
- Views (forms, tables) are designed for easy integration into FA, WordPress, or SuiteCRM, and are autoloaded via Composer.
- Platform-specific adapters/services handle integration points (e.g., FA journal entries, loan events).
- Controller uses DataProviderInterface and instantiates the correct provider for each platform (FA, WordPress, SuiteCRM) via entry points.
- Entry points for each platform define AMORTIZATION_PLATFORM and load the shared controller.
- After moving or splitting files, run `composer dump-autoload` to update autoload mappings.

## Out-of-Schedule Events
- LoanEvent model class represents skipped/extra payments.
- Platform-specific LoanEventProvider classes (FA, WordPress, SuiteCRM) implement LoanEventProviderInterface and handle CRUD for loan_events table.
- Amortization calculations must incorporate out-of-schedule events to adjust running balance, interest, and payment count.

## Testing
- Unit tests for LoanType, InterestCalcFrequency, LoanEvent model and each LoanEventProvider implementation.
- Controller and model tests for event logic integration.
- Ensure tests cover autoloaded models and views after file changes.

## UAT
- UAT scripts must include creation, editing, and deletion of out-of-schedule events.
- Verify correct impact on amortization schedule and reporting.

## Security
- User permissions for access to module features and posting to GL.
- Access Levels (FrontAccounting):
    - Loans Administrator: Full access to create, edit, delete loans, post to GL, configure module settings.
    - Loans Reader: View-only access to loan details, schedules, and reports. Cannot create, edit, delete, or post to GL.

## Design Principles
- SOLID and DRY principles
- Extensible and maintainable code
- Use phpdoc tags for documentation

## Initial Tasks
- Scaffold module directory and files
- Integrate menu entry (FA only)
- Implement basic MVC structure
- Add unit tests (PHPUnit)
- Create UML diagrams for models and architecture
- Prepare UAT scenarios and scripts
- Refactor for multi-platform support

## UML Example
```
class AmortizationModel {
    - db: DataProviderInterface
    + __construct(db: DataProviderInterface)
    + createLoan(data: array): int
    + getLoan(loan_id: int): array
    + calculateSchedule(loan_id: int): void
}
```

## Future Enhancements
- User permissions
- Reporting
- Export options
