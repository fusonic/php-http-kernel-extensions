# Upgrade 4.x to 5.0

## Changes
The signate of the `Fusonic\HttpKernelExtensions\ErrorHandler\ErrorHandlerInterface` has changed:

Before:
```
public function handleDenormalizeError(Throwable $ex): Throwable;
```

After
```
public function handleDenormalizeError(Throwable $ex, array $data, string $className): Throwable;
```

If you are using your own implementation you have to update it accordingly.

The reason for this change is that we discovered another exception case that can happen with an array of non built-in
objects in a request DTO. To provide detailed validation information the request data and DTO class name
have to be passed to the `ConstraintViolationErrorHandler`.
