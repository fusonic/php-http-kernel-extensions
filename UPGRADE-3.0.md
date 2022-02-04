# Upgrade 2.x to 3.0

## Changes
A new error handler has been added for handling Symfony validation errors. The default error handler is now set to
`Fusonic\HttpKernelExtensions\ErrorHandler\ConstraintViolationErrorHandler`. If you do not need this behaviour, you should use
your own implementation and inject that into the `Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver`.
