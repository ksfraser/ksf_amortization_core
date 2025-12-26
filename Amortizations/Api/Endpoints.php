<?php
namespace Ksfraser\Amortizations\Api;

use Ksfraser\Amortizations\Models\Loan;
use Ksfraser\Amortizations\Repositories\LoanRepository;
use Ksfraser\Amortizations\Repositories\ScheduleRepository;
use Ksfraser\Amortizations\Repositories\EventRepository;
use Ksfraser\Amortizations\Services\ScheduleGeneratorService;

/**
 * BaseApiController: Base class for all API controllers
 */
abstract class BaseApiController
{
    protected const API_VERSION = 'v1';

    /**
     * Get version
     */
    public static function getVersion(): string
    {
        return self::API_VERSION;
    }
}

/**
 * LoanController: API endpoints for loan management
 * 
 * GET    /api/v1/loans              - List all loans (with pagination)
 * POST   /api/v1/loans              - Create new loan
 * GET    /api/v1/loans/{id}         - Get loan details
 * PUT    /api/v1/loans/{id}         - Update loan
 * DELETE /api/v1/loans/{id}         - Delete loan
 */
class LoanController extends BaseApiController
{
    private LoanRepository $loanRepo;
    private ScheduleGeneratorService $scheduleGenerator;

    public function __construct(
        LoanRepository $loanRepo = null,
        ScheduleGeneratorService $scheduleGenerator = null
    ) {
        $this->loanRepo = $loanRepo ?? new LoanRepository();
        $this->scheduleGenerator = $scheduleGenerator ?? new ScheduleGeneratorService();
    }

    /**
     * GET /api/v1/loans
     * List all loans with pagination
     */
    public function list(array $queryParams = []): ApiResponse
    {
        try {
            $pagination = PaginationRequest::fromArray($queryParams);
            $pagination->validate();

            if ($pagination->hasErrors()) {
                throw new ValidationException('Invalid pagination parameters', $pagination->getErrors());
            }

            $loans = $this->loanRepo->list(
                $pagination->getOffset(),
                $pagination->getLimit()
            );

            $total = $this->loanRepo->count();

            $response = ApiResponse::success($loans, 'Loans retrieved successfully');
            $response->withPagination($pagination->getPage(), $pagination->getPerPage(), $total);

            return $response;
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve loans: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/loans
     * Create a new loan
     */
    public function create(array $requestData): ApiResponse
    {
        try {
            $request = CreateLoanRequest::fromArray($requestData);
            $request->validate();

            if ($request->hasErrors()) {
                throw new ValidationException('Loan validation failed', $request->getErrors());
            }

            $loan = new Loan();
            $loan->setPrincipal($request->getPrincipal())
                 ->setAnnualRate($request->getInterestRate())
                 ->setTermMonths($request->getTermMonths())
                 ->setStartDate($request->getStartDate())
                 ->setLoanType($request->getLoanType())
                 ->setPaymentFrequency($request->getPaymentFrequency())
                 ->setDescription($request->getLoanDescription());

            $loanId = $this->loanRepo->insert($loan);

            // Generate initial schedule
            $this->scheduleGenerator->generateSchedule($loanId);

            $loan->setId($loanId);

            return ApiResponse::created($loan->toArray(), 'Loan created successfully');
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to create loan: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/loans/{id}
     * Get loan details
     */
    public function get(int $loanId): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            return ApiResponse::success($loan->toArray(), 'Loan retrieved successfully');
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve loan: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/loans/{id}
     * Update a loan
     */
    public function update(int $loanId, array $requestData): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $request = UpdateLoanRequest::fromArray($requestData);
            $request->validate();

            if ($request->hasErrors()) {
                throw new ValidationException('Loan validation failed', $request->getErrors());
            }

            // Update only provided fields
            if (isset($requestData['principal'])) {
                $loan->setPrincipal($request->getField('principal', 0, 'float'));
            }

            if (isset($requestData['interest_rate'])) {
                $loan->setAnnualRate($request->getField('interest_rate', 0, 'float'));
            }

            if (isset($requestData['start_date'])) {
                $loan->setStartDate($request->getField('start_date', '', 'string'));
            }

            if (isset($requestData['description'])) {
                $loan->setDescription($request->getField('description', '', 'string'));
            }

            $this->loanRepo->update($loan);

            return ApiResponse::success($loan->toArray(), 'Loan updated successfully');
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to update loan: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/loans/{id}
     * Delete a loan
     */
    public function delete(int $loanId): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $this->loanRepo->delete($loanId);

            return ApiResponse::success(null, 'Loan deleted successfully', 204);
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to delete loan: ' . $e->getMessage());
        }
    }
}

/**
 * ScheduleController: API endpoints for schedule management
 * 
 * GET    /api/v1/loans/{loanId}/schedules        - Get payment schedule
 * POST   /api/v1/loans/{loanId}/schedules        - Generate/regenerate schedule
 * PUT    /api/v1/loans/{loanId}/schedules/{id}   - Update schedule row
 * DELETE /api/v1/loans/{loanId}/schedules        - Delete schedule rows after date
 */
class ScheduleController extends BaseApiController
{
    private ScheduleRepository $scheduleRepo;
    private LoanRepository $loanRepo;
    private ScheduleGeneratorService $scheduleGenerator;

    public function __construct(
        ScheduleRepository $scheduleRepo = null,
        LoanRepository $loanRepo = null,
        ScheduleGeneratorService $scheduleGenerator = null
    ) {
        $this->scheduleRepo = $scheduleRepo ?? new ScheduleRepository();
        $this->loanRepo = $loanRepo ?? new LoanRepository();
        $this->scheduleGenerator = $scheduleGenerator ?? new ScheduleGeneratorService();
    }

    /**
     * GET /api/v1/loans/{loanId}/schedules
     * Get payment schedule for a loan
     */
    public function list(int $loanId, array $queryParams = []): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $pagination = PaginationRequest::fromArray($queryParams);
            $pagination->validate();

            if ($pagination->hasErrors()) {
                throw new ValidationException('Invalid pagination parameters', $pagination->getErrors());
            }

            $schedule = $this->scheduleRepo->getScheduleForLoan(
                $loanId,
                $pagination->getOffset(),
                $pagination->getLimit()
            );

            $total = $this->scheduleRepo->countScheduleRows($loanId);

            $response = ApiResponse::success($schedule, 'Schedule retrieved successfully');
            $response->withPagination($pagination->getPage(), $pagination->getPerPage(), $total);

            return $response;
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve schedule: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/loans/{loanId}/schedules
     * Generate or regenerate schedule for a loan
     */
    public function generate(int $loanId, array $requestData = []): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $request = CreateScheduleRequest::fromArray(['loan_id' => $loanId] + $requestData);
            $request->validate();

            if ($request->hasErrors()) {
                throw new ValidationException('Schedule generation failed', $request->getErrors());
            }

            // Generate schedule
            $this->scheduleGenerator->generateSchedule($loanId, $request->shouldRecalculate());

            $schedule = $this->scheduleRepo->getScheduleForLoan($loanId);

            return ApiResponse::success($schedule, 'Schedule generated successfully', 201);
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate schedule: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/loans/{loanId}/schedules
     * Delete schedule rows after a specific date
     */
    public function deleteAfterDate(int $loanId, array $requestData): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $request = PaginationRequest::fromArray($requestData);
            if (isset($requestData['after_date'])) {
                $date = $requestData['after_date'];
                $d = \DateTime::createFromFormat('Y-m-d', $date);
                if (!$d || $d->format('Y-m-d') !== $date) {
                    throw new ValidationException('Invalid date', ['after_date' => ['Invalid date format, expected YYYY-MM-DD']]);
                }
            } else {
                throw new ValidationException('Missing required field', ['after_date' => ['after_date is required']]);
            }

            $this->scheduleRepo->deleteScheduleAfterDate($loanId, $requestData['after_date']);

            return ApiResponse::success(null, 'Schedule rows deleted successfully', 204);
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to delete schedule rows: ' . $e->getMessage());
        }
    }
}

/**
 * EventController: API endpoints for loan event management
 * 
 * GET    /api/v1/loans/{loanId}/events        - Get loan events
 * POST   /api/v1/loans/{loanId}/events        - Record new event
 * GET    /api/v1/loans/{loanId}/events/{id}   - Get event details
 * DELETE /api/v1/loans/{loanId}/events/{id}   - Delete event
 */
class EventController extends BaseApiController
{
    private EventRepository $eventRepo;
    private LoanRepository $loanRepo;

    public function __construct(
        EventRepository $eventRepo = null,
        LoanRepository $loanRepo = null
    ) {
        $this->eventRepo = $eventRepo ?? new EventRepository();
        $this->loanRepo = $loanRepo ?? new LoanRepository();
    }

    /**
     * GET /api/v1/loans/{loanId}/events
     * Get all events for a loan
     */
    public function list(int $loanId, array $queryParams = []): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $pagination = PaginationRequest::fromArray($queryParams);
            $pagination->validate();

            if ($pagination->hasErrors()) {
                throw new ValidationException('Invalid pagination parameters', $pagination->getErrors());
            }

            $events = $this->eventRepo->getEventsForLoan(
                $loanId,
                $pagination->getOffset(),
                $pagination->getLimit()
            );

            $total = $this->eventRepo->countEventsForLoan($loanId);

            $response = ApiResponse::success($events, 'Events retrieved successfully');
            $response->withPagination($pagination->getPage(), $pagination->getPerPage(), $total);

            return $response;
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve events: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/loans/{loanId}/events
     * Record a new event (extra payment, skip payment, rate change, etc)
     */
    public function record(int $loanId, array $requestData): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $request = RecordEventRequest::fromArray(['loan_id' => $loanId] + $requestData);
            $request->validate();

            if ($request->hasErrors()) {
                throw new ValidationException('Event validation failed', $request->getErrors());
            }

            $event = $this->eventRepo->createEvent(
                $loanId,
                $request->getEventType(),
                $request->getEventDate(),
                $request->getAmount(),
                $request->getNotes()
            );

            return ApiResponse::created($event->toArray(), 'Event recorded successfully');
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to record event: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/loans/{loanId}/events/{eventId}
     * Get event details
     */
    public function get(int $loanId, int $eventId): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            if ($eventId <= 0) {
                throw new ValidationException('Invalid event ID', ['event_id' => ['Event ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $event = $this->eventRepo->findById($eventId);

            if (!$event || $event->getLoanId() !== $loanId) {
                throw new ResourceNotFoundException("Event with ID $eventId not found for loan $loanId");
            }

            return ApiResponse::success($event->toArray(), 'Event retrieved successfully');
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve event: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/loans/{loanId}/events/{eventId}
     * Delete an event
     */
    public function delete(int $loanId, int $eventId): ApiResponse
    {
        try {
            if ($loanId <= 0) {
                throw new ValidationException('Invalid loan ID', ['loan_id' => ['Loan ID must be positive']]);
            }

            if ($eventId <= 0) {
                throw new ValidationException('Invalid event ID', ['event_id' => ['Event ID must be positive']]);
            }

            $loan = $this->loanRepo->findById($loanId);

            if (!$loan) {
                throw new ResourceNotFoundException("Loan with ID $loanId not found");
            }

            $event = $this->eventRepo->findById($eventId);

            if (!$event || $event->getLoanId() !== $loanId) {
                throw new ResourceNotFoundException("Event with ID $eventId not found for loan $loanId");
            }

            $this->eventRepo->delete($eventId);

            return ApiResponse::success(null, 'Event deleted successfully', 204);
        } catch (ValidationException $e) {
            return $e->toResponse();
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to delete event: ' . $e->getMessage());
        }
    }
}
