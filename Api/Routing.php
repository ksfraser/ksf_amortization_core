<?php
namespace Ksfraser\Amortizations\Api;

/**
 * ApiRouter: Routes HTTP requests to appropriate API endpoints
 * 
 * Usage:
 * $router = new ApiRouter();
 * $response = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['PATH_INFO'], $_POST);
 * $response->send();
 */
class ApiRouter
{
    private LoanController $loanController;
    private ScheduleController $scheduleController;
    private EventController $eventController;

    public function __construct(
        LoanController $loanController = null,
        ScheduleController $scheduleController = null,
        EventController $eventController = null
    ) {
        $this->loanController = $loanController ?? new LoanController();
        $this->scheduleController = $scheduleController ?? new ScheduleController();
        $this->eventController = $eventController ?? new EventController();
    }

    /**
     * Dispatch HTTP request to appropriate controller
     */
    public function dispatch(string $method, string $path, array $data = []): ApiResponse
    {
        try {
            // Parse path and extract segments
            $segments = $this->parsePath($path);

            // Route to appropriate handler
            return match (true) {
                // LOAN ROUTES
                $this->matches($segments, ['api', 'v1', 'loans']) && $method === 'GET' 
                    => $this->loanController->list($data),

                $this->matches($segments, ['api', 'v1', 'loans']) && $method === 'POST'
                    => $this->loanController->create($data),

                $this->matchesWithId($segments, ['api', 'v1', 'loans']) && $method === 'GET'
                    => $this->loanController->get((int)end($segments)),

                $this->matchesWithId($segments, ['api', 'v1', 'loans']) && $method === 'PUT'
                    => $this->loanController->update((int)end($segments), $data),

                $this->matchesWithId($segments, ['api', 'v1', 'loans']) && $method === 'DELETE'
                    => $this->loanController->delete((int)end($segments)),

                // SCHEDULE ROUTES
                $this->matches($segments, ['api', 'v1', 'loans', null, 'schedules']) && $method === 'GET'
                    => $this->scheduleController->list((int)$segments[3], $data),

                $this->matches($segments, ['api', 'v1', 'loans', null, 'schedules']) && $method === 'POST'
                    => $this->scheduleController->generate((int)$segments[3], $data),

                $this->matches($segments, ['api', 'v1', 'loans', null, 'schedules']) && $method === 'DELETE'
                    => $this->scheduleController->deleteAfterDate((int)$segments[3], $data),

                // EVENT ROUTES
                $this->matches($segments, ['api', 'v1', 'loans', null, 'events']) && $method === 'GET'
                    => $this->eventController->list((int)$segments[3], $data),

                $this->matches($segments, ['api', 'v1', 'loans', null, 'events']) && $method === 'POST'
                    => $this->eventController->record((int)$segments[3], $data),

                $this->matchesWithEventId($segments, ['api', 'v1', 'loans', null, 'events']) && $method === 'GET'
                    => $this->eventController->get((int)$segments[3], (int)end($segments)),

                $this->matchesWithEventId($segments, ['api', 'v1', 'loans', null, 'events']) && $method === 'DELETE'
                    => $this->eventController->delete((int)$segments[3], (int)end($segments)),

                default => ApiResponse::error('Not found', null, 404),
            };
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiResponse::serverError('Internal server error: ' . $e->getMessage());
        }
    }

    /**
     * Parse URL path into segments
     */
    private function parsePath(string $path): array
    {
        // Remove leading/trailing slashes
        $path = trim($path, '/');
        
        // Split by /
        $segments = explode('/', $path);
        
        // Filter empty segments
        return array_filter($segments, fn($s) => !empty($s));
    }

    /**
     * Check if segments match pattern (null = any segment)
     */
    private function matches(array $segments, array $pattern): bool
    {
        if (count($segments) !== count($pattern)) {
            return false;
        }

        foreach ($pattern as $i => $p) {
            if ($p !== null && $segments[$i] !== $p) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if segments match pattern with numeric ID at end
     */
    private function matchesWithId(array $segments, array $pattern): bool
    {
        $patternWithId = array_merge($pattern, [null]);
        
        if (count($segments) !== count($patternWithId)) {
            return false;
        }

        foreach ($patternWithId as $i => $p) {
            if ($i === count($patternWithId) - 1) {
                // Last segment must be numeric
                if (!is_numeric($segments[$i])) {
                    return false;
                }
            } elseif ($p !== null && $segments[$i] !== $p) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if segments match pattern with loan ID and event ID
     */
    private function matchesWithEventId(array $segments, array $pattern): bool
    {
        $patternWithId = array_merge($pattern, [null]);
        
        if (count($segments) !== count($patternWithId)) {
            return false;
        }

        foreach ($patternWithId as $i => $p) {
            if ($i === 3) {
                // Loan ID must be numeric
                if (!is_numeric($segments[$i])) {
                    return false;
                }
            } elseif ($i === count($patternWithId) - 1) {
                // Event ID must be numeric
                if (!is_numeric($segments[$i])) {
                    return false;
                }
            } elseif ($p !== null && $segments[$i] !== $p) {
                return false;
            }
        }

        return true;
    }
}

/**
 * ApiDispatcher: Handles HTTP request/response dispatch
 * 
 * Can be used as entry point for API requests
 */
class ApiDispatcher
{
    private ApiRouter $router;

    public function __construct(ApiRouter $router = null)
    {
        $this->router = $router ?? new ApiRouter();
    }

    /**
     * Handle incoming request and send response
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        
        // Get request body
        $data = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $content = file_get_contents('php://input');
            $data = json_decode($content, true) ?? [];
        } elseif ($method === 'GET') {
            $data = $_GET;
        }

        // Route request
        $response = $this->router->dispatch($method, $path, $data);

        // Send response
        $response->send();
    }

    /**
     * Handle request and return response (for testing)
     */
    public function handleRequestReturning(): ApiResponse
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        
        // Get request body
        $data = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $content = file_get_contents('php://input');
            $data = json_decode($content, true) ?? [];
        } elseif ($method === 'GET') {
            $data = $_GET;
        }

        return $this->router->dispatch($method, $path, $data);
    }
}
