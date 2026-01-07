# Backend Styleguide

## Principles

1. **Thin controllers** - Controllers only handle HTTP, delegate logic to services
2. **Single responsibility** - Each service handles one domain concern
3. **Dependency injection** - Use constructor injection, avoid service locator
4. **Type safety** - Use strict types, typed properties, return types

## Directory Structure

```
src/
├── Command/           # CLI commands
├── Controller/        # HTTP controllers
├── Entity/            # Doctrine entities
│   ├── Content/       # Feed content entities
│   ├── Subscriptions/ # Subscription entities
│   └── Users/         # User entities
├── EventListener/     # Doctrine/Symfony event listeners
├── EventSubscriber/   # Symfony event subscribers
├── Form/              # Symfony form types
├── Message/           # Messenger messages
├── MessageHandler/    # Messenger handlers
├── Repository/        # Doctrine repositories
│   ├── Content/
│   ├── Subscriptions/
│   └── Users/
├── Scheduler/         # Symfony scheduler providers
├── Security/          # Authenticators, voters
├── Service/           # Business logic services
└── Twig/              # Twig extensions
```

## Naming Conventions

### Controllers
- Suffix: `Controller`
- One controller per feature area
- Examples: `FeedController`, `AuthController`, `SubscriptionController`

### Services
- Suffix: `Service`
- Domain-specific naming
- Examples: `FeedFetcher`, `TotpService`, `UserRegistrationService`

### Repositories
- Suffix: `Repository`
- Match entity name
- Examples: `UserRepository`, `FeedItemRepository`

### Form Types
- Suffix: `Type`
- Match form purpose
- Examples: `LoginType`, `SetupType`, `SubscriptionsType`

### Entities
- No suffix
- Singular names
- Examples: `User`, `Subscription`, `FeedItem`

## Controller Guidelines

### Good
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

### Bad
```php
#[Route('/login', name: 'auth_login')]
public function login(Request $request): Response
{
    // Don't do validation in controller
    $email = $request->request->get('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // ...
    }
    
    // Don't do database queries in controller
    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
}
```

## Service Guidelines

### Single Responsibility
```php
// Good: One service, one purpose
class TotpService {
    public function generateSecret(): string { }
    public function verify(string $secret, string $code): bool { }
    public function getQrCodeDataUri(string $secret): string { }
}

// Bad: Mixed responsibilities
class AuthService {
    public function login() { }
    public function register() { }
    public function sendEmail() { }
    public function generatePdf() { }
}
```

### Constructor Injection
```php
// Good
public function __construct(
    private UserRepository $userRepository,
    private UserPasswordHasherInterface $passwordHasher,
) {}

// Bad
public function register() {
    $repo = $this->container->get(UserRepository::class);
}
```

## Form Type Guidelines

### Use Symfony Validator Constraints
```php
->add('email', EmailType::class, [
    'constraints' => [
        new Assert\NotBlank(message: 'Email is required.'),
        new Assert\Email(message: 'Please enter a valid email.'),
    ],
])
```

### Configure CSRF Protection
```php
public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->setDefaults([
        'csrf_protection' => true,
        'csrf_token_id' => 'unique_form_id',
    ]);
}
```

## Entity Guidelines

### Use Typed Properties
```php
#[ORM\Entity]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;
}
```

### Implement Interfaces
- `UserInterface` for users
- `PasswordAuthenticatedUserInterface` for password auth

## Repository Guidelines

### Custom Query Methods
```php
public function findByEmail(string $email): ?User
{
    return $this->findOneBy(['email' => $email]);
}

public function hasAnyUser(): bool
{
    return $this->count([]) > 0;
}
```

## Security Guidelines

1. **Rate limiting** - Use `symfony/rate-limiter` for login attempts
2. **CSRF protection** - Enable on all state-changing forms
3. **Password hashing** - Use `UserPasswordHasherInterface`
4. **Input validation** - Use Symfony Validator, never trust user input
5. **TOTP secrets** - Generate locally, never use external services

## Testing

- Unit tests in `tests/Unit/`
- Integration tests in `tests/Integration/`
- Controller tests in `tests/Controller/`
- Use `WebTestCase` for HTTP tests
