# http-kernel-extensions

[![License](https://img.shields.io/packagist/l/fusonic/http-kernel-extensions?color=blue)](https://github.com/fusonic/php-http-kernel-extensions/blob/master/LICENSE)
[![Latest Version](https://img.shields.io/github/tag/fusonic/php-http-kernel-extensions.svg?color=blue)](https://github.com/fusonic/php-http-kernel-extensions/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/fusonic/http-kernel-extensions.svg?color=blue)](https://packagist.org/packages/fusonic/http-kernel-extensions)
![php 7.4+](https://img.shields.io/badge/php-min%207.4-blue.svg)

* [About](#about)
* [Install](#install)
* [Usage](#usage)

## About

This library contains a variety of extensions to
the [Symfony HttpKernel component](https://symfony.com/doc/current/components/http_kernel.html). See below for details
on each extension this lib provides and how it works.

Currently primary development takes place at a private repository at Gitlab.com. The project on Github.com is updated
regularly, but does not include any issues managed at Gitlab. However, we are happily accepting issues and pull requests
on Github as well! Feel free to open an issue or merge request. If we see broader community engagement in the future, we
may consider switching our primary development to Github.

## Install

Use composer to install the lib from packagist.

```bash
composer require fusonic/http-kernel-extensions
```

## Usage

### The RequestDtoResolver

In Symfony there exists a thing
called [argument resolvers](https://symfony.com/doc/current/controller/argument_value_resolver.html). They can be used
to set the value of controller action arguments before the actions get called. There exists e.g.
the `RequestValueResolver` which will inject the current request as an argument in the called action. Similar to this we
created our own argument resolver, but it does a few more things than just injecting an object.

#### What does it do?

Our `RequestDtoResolver` can be used to map requests data directly to objects. Instead of manually getting all the
information from your request and putting it in an object or - god forbid - passing around generic data arrays, this
class will leverage the Symfony Serializer to map requests to objects and by that enable you to have custom objects to
transport the request data (aka data transfer objects) from your controller to your business logic. In addition, it will
also validate the resulting object with Symfony Validation if you set validation annotations.

- Mapping will happen for parameters accompanied by
  the `Fusonic\HttpKernelExtensions\Attribute\FromRequest` [attribute](src/Attribute/FromRequest.php). Alternatively the
  attribute can also be set on the class of the parameter (see example below).
- Strong type checks will be enforced for PUT, POST, PATCH and DELETE during serialization and it will result in an
  error if the types in the request body don't match the expected ones in the DTO.
- Type enforcement will be disabled for all other requests e.g. GET as query parameters will always be transferred as
  string.
- The request body will be combined with route parameters for PUT, POST, PATCH and DELETE requests (query parameters
  will be ignored in this case).
- The query parameters will be combined with route parameters for all other requests (request body will be ignored in
  this case).
- Route parameters will always override query parameters or request body values with the same name.
- After deserializing the request to an object, validation will take place.
- A `BadRequestHttpException` will be thrown when
  - the resulting DTO object is invalid according to Symfony Validation
  - the request body can't be deserialized
  - the request contains invalid JSON
  - the request contains valid JSON but the hierarchy levels exceeds 512
- If you are using the [ConstraintViolationErrorHandler](src/ErrorHandler/ConstraintViolationErrorHandler.php) error handler, a
  [ConstraintViolationException](src/Exception/ConstraintViolationException.php) will be thrown if the validation of your object
  fails. You can also implement your own handler by implementing the [ErrorHandlerInterface](src/ErrorHandler/ErrorHandlerInterface.php).
- Depending on the given content type it will either parse the request body as a regular form or parse the content as JSON
  if the content type is set accordingly.

### How to use?

Supposing you are using a full Symfony setup you have to register the resolver as a service in your `services.yaml` as
shown below to be called by Symfony.

```yaml
    Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver:
        tags:
            - { name: controller.argument_value_resolver, priority: 50 }
```

Create your DTO like e.g. our `UpdateFooDto` here. All the validation stuff is optional but getters and setters are
needed by the serializer.

```php

// ...

final class UpdateFooDto
{
    #[Assert\NotNull]
    #[Assert\Positive]
    private int $id;

    #[Assert\NotBlank]
    private string $clientVersion;

    #[Assert\NotNull]
    private array $browserInfo;

    public function getClientVersion(): string
    {
        return $this->clientVersion;
    }

    public function setClientVersion(string $clientVersion): void
    {
        $this->clientVersion = $clientVersion;
    }

    public function getBrowserInfo(): array
    {
        return $this->browserInfo;
    }

    public function setBrowserInfo(array $browserInfo): void
    {
        $this->browserInfo = $browserInfo;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
```

#### Parameter attribute

Finally, add the DTO with the `RequestDtoArgument` to your controller action. Routing parameters are optional as well of
course.

```php

// ...
use Fusonic\HttpKernelExtensions\Attribute\FromRequest;

final class FooController extends AbstractController
{
    /**
     * @Route("/{id}/update", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function updateAction(#[FromRequest] UpdateFooDto $dto): Response
    {
        // do something with your $dto here
    }
}
```

#### Class attribute

Alternatively you can also add the attribute to the DTO class itself instead of the parameter in the controller action
if you prefer it this way.

```php

// ...
use Fusonic\HttpKernelExtensions\Attribute\FromRequest;

#[FromRequest]
final class UpdateFooDto
{
// ...
}
```

```php

// ...

final class FooController extends AbstractController
{
    /**
     * @Route("/{id}/update", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function updateAction(UpdateFooDto $dto): Response
    {
        // do something with your $dto here
    }
}
```

#### Parsing and collecting data for models
By default, any `json` or `form` request body types will be parsed accordingly. To override this behaviour you could
inject your own request body parsers (by implementing `Fusonic\HttpKernelExtensions\Request\BodyParser\RequestBodyParserInterface`)
into an implementation of `Fusonic\HttpKernelExtensions\Request\RequestDataCollectorInterface`, which is injected into the
`Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver`. Inside the `RequestDataCollectorInterface` you can
also modify the behaviour of how and which values are used from the `Request` object.

#### Error handling

The extension provides a default error handler (`http-kernel-extensions/src/ErrorHandler/ConstraintViolationErrorHandler.php`) which
handles common de-normalization errors that should be considered type errors. It will create a
`Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException` [ConstraintViolationException](src/Exception/ConstraintViolationException.php)
which can be used with the provided `Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer` [ConstraintViolationExceptionNormalizer](src/Normalizer/ConstraintViolationExceptionNormalizer.php).
This normalizer is uses on Symfony's built-in `Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer` and enhances it
with some extra information: an `errorCode` and `messageTemplate`. Both useful for parsing validation errors on the client side.
If that does not match your needs you can simply provide your own error handler by implementing
the `Fusonic\HttpKernelExtensions\ErrorHandler\ErrorHandlerInterface` and passing it to the `RequestDtoResolver`.

You have to register the normalizer as a service like this:

```yaml
  Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer:
        arguments:
            - "@serializer.normalizer.constraint_violation_list"
        tags:
          - { name: serializer.normalizer }
```

##### Using an exception listener/subscriber
In Symfony you can use an exception listener or subscriber to eventually convert the `ConstraintViolationException` into an actual response using
the `Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer`. For example:

```php

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class ExceptionSubscriber implements EventSubscriberInterface {

    public function __construct(private NormalizerInterface $normalizer) 
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        
        if ($throwable instanceof ConstraintViolationException) {
            $data = $this->normalizer->normalize($throwable);
            $event->setResponse(new JsonResponse($data, 422));
        }
    }
}
```

Check the [Events and Event Listeners](https://symfony.com/doc/current/event_dispatcher.html) for details.

#### ContextAwareProvider

There are cases where you want to add data to your DTOs but not through the consumer of the API but, for example, depending on the currently logged in user. You could do that manually after you received your DTO in the controller, get the user, set the user for the DTO and then move on with the processing. As you set it after the creation of the DTO you cannot work with the validation and have to make it nullable as well. And you might have to do some additional checks in your business logic afterwards to ensure everything you need is set.

Or you just create and register a provider, implement (and test) it once and be done with it. All providers will be called by the `RequestDtoResolver`, retrieve the needed data for the supported DTO, set it in your DTO and then the validation will take place. By the time you get it in your controller it's complete and validated. How do you do that?

1. Create a provider and implement the two methods of the `ContextAwareProvideInterface`.

```php
<?php

// ...

final class UserIdAwareProvider implements ContextAwareProviderInterface
{
    public function __construct(private UserProviderInterface $userProvider)
    {
    }

    public function supports(object $dto): bool
    {
        return $dto instanceof UserIdAwareInterface;
    }

    public function provide(object $dto): void
    {
        if(!($dto instanceof UserIdAwareInterface)){
            throw new \LogicException('Object is no instance of '.UserIdAwareInterface::class);
        }

        $user = $this->userProvider->getUser();
        $dto->withUserId($user->getId());
    }
}
```

2. Create the interface to mark the class you support and set the data.

```php
<?php

//... 

interface UserIdAwareInterface
{
    public function withUserId(int $id): void;
}

```

3. Implement the interface in the DTO.
4. Finally, pass the providers into the resolver. If you are using Symfony you will be doing that in the `services.yaml` and it will look similar to this.
```yaml
#...
    _instanceof:
        Fusonic\HttpKernelExtensions\Provider\ContextAwareProviderInterface:
            tags:
                - {name: fusonic.http_kernel_extensions.context_aware_provider}

    Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver:
        tags:
            - { name: controller.argument_value_resolver, priority: 50 }
        arguments:
            - '@serializer'
            - '@validator'
            - null
            - !tagged_iterator fusonic.http_kernel_extensions.context_aware_provider
```
