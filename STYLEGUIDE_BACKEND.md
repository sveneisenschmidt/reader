# Backend Styleguide

## Principles

- Thin controllers, logic in services
- Constructor injection
- Strict types everywhere

## Code Style

- Use **single quotes** (`'`) for strings, not double quotes (`"`)
- String concatenation: use `.` with spaces (e.g., `'Hello ' . $name`)
- Arrow functions: space before parenthesis (e.g., `fn ($x) => $x + 1`)
- Run `make lint-fix` to auto-format code

## Directory Structure

Top-level directories in `src/`: Command, Controller, Domain, Enum, EventListener, EventSubscriber, Form, Message, MessageHandler, Messenger, Repository, Scheduler, Security, Service, Twig.

Domain entities are grouped by context: Content, Messages, Subscriptions, Users.

## Naming

- Controllers: `*Controller`
- Services: `*Service` or domain-specific (`FeedFetcher`)
- Repositories: `*Repository`
- Forms: `*Type`
- Entities: singular, no suffix

## Controllers

```php
#[Route('/login', name: 'auth_login')]
public function login(Request $request): Response
{
    $form = $this->createForm(LoginType::class);
    
    return $this->render('auth/login.html.twig', [
        'form' => $form,
    ]);
}
```

No database queries or business logic in controllers.

## Services

```php
public function __construct(
    private UserRepository $userRepository,
    private UserPasswordHasherInterface $passwordHasher,
) {}
```

One service, one responsibility.

## Entities

Multiple entity managers by domain:
- `users` - User, ReadStatus, SeenStatus
- `subscriptions` - Subscription
- `content` - FeedItem
- `messages` - ProcessedMessage

## Forms

Use validator constraints, enable CSRF:

```php
->add('email', EmailType::class, [
    'constraints' => [
        new Assert\NotBlank(),
        new Assert\Email(),
    ],
])
```

## Security

- CSRF on all forms
- Password hashing via `UserPasswordHasherInterface`
- TOTP for 2FA
- Route protection via `access_control` in security.yaml

## Tests

Test directories: Controller (WebTestCase), Integration, Unit.

Run: `make test`
