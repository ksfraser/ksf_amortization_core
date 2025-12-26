<?php
namespace Ksfraser\Amortizations\Api;

use JsonSerializable;

/**
 * ApiResponse: Standard API response wrapper with metadata
 */
class ApiResponse implements JsonSerializable
{
    private bool $success;
    private string $message;
    private mixed $data = null;
    private ?array $errors = null;
    private int $statusCode;
    private ?array $pagination = null;
    private array $meta;

    private function __construct(
        bool $success,
        string $message,
        mixed $data = null,
        ?array $errors = null,
        int $statusCode = 200,
        ?array $pagination = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->errors = $errors;
        $this->statusCode = $statusCode;
        $this->pagination = $pagination;
        $this->meta = [
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'requestId' => $this->generateRequestId(),
        ];
    }

    /**
     * Create successful response
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        ?array $pagination = null
    ): self {
        return new self(true, $message, $data, null, $statusCode, $pagination);
    }

    /**
     * Create error response
     */
    public static function error(
        string $message,
        ?array $errors = null,
        int $statusCode = 400
    ): self {
        return new self(false, $message, null, $errors, $statusCode);
    }

    /**
     * Create validation error response
     */
    public static function validationError(array $errors, int $statusCode = 422): self
    {
        return self::error('Validation failed', $errors, $statusCode);
    }

    /**
     * Create not found response
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, null, 404);
    }

    /**
     * Create unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, null, 401);
    }

    /**
     * Create forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, null, 403);
    }

    /**
     * Create conflict response
     */
    public static function conflict(string $message, ?array $errors = null): self
    {
        return self::error($message, $errors, 409);
    }

    /**
     * Create rate limit response
     */
    public static function tooManyRequests(string $message = 'Too many requests'): self
    {
        return self::error($message, null, 429);
    }

    /**
     * Create created response (201)
     */
    public static function created(
        mixed $data,
        string $message = 'Resource created successfully'
    ): self {
        return self::success($data, $message, 201);
    }

    /**
     * Create no content response (204)
     */
    public static function noContent(): self
    {
        return self::success(null, 'No content', 204);
    }

    /**
     * Create server error response
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::error($message, null, 500);
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response body as array
     */
    public function toArray(): array
    {
        $body = [
            'success' => $this->success,
            'message' => $this->message,
            'meta' => $this->meta,
        ];

        if ($this->data !== null || $this->success) {
            $body['data'] = $this->data;
        }

        if ($this->errors !== null && !empty($this->errors)) {
            $body['errors'] = $this->errors;
        }

        if ($this->pagination !== null) {
            $body['pagination'] = $this->pagination;
        }

        return $body;
    }

    /**
     * Get response body as JSON string
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get response body with HTTP headers
     */
    public function send(): void
    {
        header('Content-Type: application/json');
        header('HTTP/1.1 ' . $this->statusCode . ' ' . $this->getHttpStatusMessage());
        echo $this->toJson();
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return 'req-' . bin2hex(random_bytes(8));
    }

    /**
     * Get HTTP status message
     */
    private function getHttpStatusMessage(): string
    {
        return match ($this->statusCode) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Unknown',
        };
    }

    /**
     * Add pagination metadata
     */
    public function withPagination(int $page, int $perPage, int $total): self
    {
        $this->pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int)ceil($total / $perPage),
        ];
        return $this;
    }

    /**
     * Add custom metadata
     */
    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }
}

/**
 * ApiException: Base exception for API errors
 */
abstract class ApiException extends \Exception
{
    protected int $statusCode = 400;
    protected ?array $errors = null;

    public function __construct(
        string $message,
        ?array $errors = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function toResponse(): ApiResponse
    {
        if ($this->errors) {
            return ApiResponse::error($this->getMessage(), $this->errors, $this->statusCode);
        }
        return ApiResponse::error($this->getMessage(), null, $this->statusCode);
    }
}

/**
 * ValidationException: Thrown on validation errors (422)
 */
class ValidationException extends ApiException
{
    protected int $statusCode = 422;
}

/**
 * AuthenticationException: Thrown on auth failures (401)
 */
class AuthenticationException extends ApiException
{
    protected int $statusCode = 401;
}

/**
 * AuthorizationException: Thrown on permission denied (403)
 */
class AuthorizationException extends ApiException
{
    protected int $statusCode = 403;
}

/**
 * ResourceNotFoundException: Thrown on resource not found (404)
 */
class ResourceNotFoundException extends ApiException
{
    protected int $statusCode = 404;
}

/**
 * ConflictException: Thrown on resource conflict (409)
 */
class ConflictException extends ApiException
{
    protected int $statusCode = 409;
}

/**
 * RateLimitException: Thrown on rate limit exceeded (429)
 */
class RateLimitException extends ApiException
{
    protected int $statusCode = 429;
}

/**
 * InternalServerException: Thrown on server error (500)
 */
class InternalServerException extends ApiException
{
    protected int $statusCode = 500;
}

/**
 * BadRequestException: Thrown on bad request (400)
 */
class BadRequestException extends ApiException
{
    protected int $statusCode = 400;
}

/**
 * PaginatedResponse: Response with pagination support
 */
class PaginatedResponse extends ApiResponse
{
    public function __construct(
        array $items,
        int $page,
        int $perPage,
        int $total,
        string $message = 'Success'
    ) {
        parent::__construct(
            true,
            $message,
            $items,
            null,
            200,
            [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / $perPage),
            ]
        );
    }
}
