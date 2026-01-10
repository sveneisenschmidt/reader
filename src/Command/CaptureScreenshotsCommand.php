<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use App\Entity\Messages\ProcessedMessage;
use App\Entity\Users\User;
use App\Message\RefreshFeedsMessage;
use App\Repository\Messages\ProcessedMessageRepository;
use App\Repository\Users\UserRepository;
use App\Service\FeedContentService;
use App\Service\FeedViewService;
use App\Service\ReadStatusService;
use App\Service\SeenStatusService;
use App\Service\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CodeCoverageIgnore]
#[When(env: "dev")]
#[When(env: "test")]
#[
    AsCommand(
        name: "reader:capture-screenshots",
        description: "Capture screenshots of all app pages for documentation",
    ),
]
class CaptureScreenshotsCommand extends Command
{
    private const TEST_EMAIL = "screenshots@reader.test";
    private const TEST_PASSWORD = "screenshot-password-123";
    private const SCREENSHOT_DIR = "docs/screenshots";
    private const VIEWPORT_WIDTH = 1280;
    private const VIEWPORT_HEIGHT = 1024;
    // Chrome enforces minimum width of 500px, so we use that
    // Height needs +139px offset for Chrome UI in headless mode
    private const MOBILE_VIEWPORT_WIDTH = 500;
    private const MOBILE_VIEWPORT_HEIGHT = 991; // 852 + 139 = actual viewport 852

    private const TEST_FEEDS = [
        [
            "url" => "https://sven.eisenschmidt.website/index.xml",
            "title" => "Sven's Blog",
            "folder" => "Personal",
        ],
        [
            "url" => "https://jasper.tandy.is/syndicated",
            "title" => "Jasper's Blog",
            "folder" => "Personal",
        ],
        [
            "url" => "https://news.ycombinator.com/rss",
            "title" => "Hacker News",
            "folder" => "News",
        ],
    ];

    private ?RemoteWebDriver $driver = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TotpService $totpService,
        private FeedViewService $feedViewService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
        private FeedContentService $feedContentService,
        private ProcessedMessageRepository $processedMessageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            "base-url",
            null,
            InputOption::VALUE_REQUIRED,
            "Base URL of the app",
            "http://127.0.0.1:8000",
        )->addOption(
            "chromedriver-url",
            null,
            InputOption::VALUE_REQUIRED,
            "ChromeDriver URL",
            "http://localhost:9515",
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $baseUrl = $input->getOption("base-url");
        $chromedriverUrl = $input->getOption("chromedriver-url");

        $io->title("Capturing Screenshots");

        // Ensure screenshot directory exists
        $screenshotDir = $this->getScreenshotDir();
        if (!is_dir($screenshotDir)) {
            mkdir($screenshotDir, 0755, true);
        }

        try {
            // Setup WebDriver
            $io->section("Setting up WebDriver");
            $this->setupWebDriver($chromedriverUrl);
            $io->success("WebDriver connected");

            // Check if we need to set up a user
            $user = $this->userRepository->findByEmail(self::TEST_EMAIL);
            $totpSecret = null;

            if (!$user) {
                // Capture setup page first (only visible when no user exists)
                $io->section("Capturing Setup");
                $this->driver->get($baseUrl . "/setup");
                $this->waitForPage();
                $this->takeScreenshot("setup");
                $io->success("setup.png");

                // Create user programmatically for remaining screenshots
                $io->section("Creating test user");
                $totpSecret = $this->totpService->generateSecret();
                $user = $this->createTestUser($totpSecret);
                $io->success("Test user created");
            } else {
                $totpSecret = $user->getTotpSecret();
                $io->warning("User already exists, skipping setup screenshot");
            }

            // Capture login page (now that user exists)
            $io->section("Capturing Login");
            $this->driver->get($baseUrl . "/login");
            $this->waitForPage();
            $this->takeScreenshot("login");
            $io->success("login.png");

            // Login
            $io->section("Logging in");
            $this->login($baseUrl, $totpSecret);
            $io->success("Logged in");

            // Check if onboarding is needed (no feeds)
            $this->driver->get($baseUrl . "/");
            $this->waitForPage();

            $currentUrl = $this->driver->getCurrentURL();
            if (str_contains($currentUrl, "onboarding")) {
                // Capture onboarding
                $io->section("Capturing Onboarding");
                $this->takeScreenshot("onboarding");
                $io->success("onboarding.png");

                // Add feeds via subscriptions page
                // Feeds are automatically refreshed when added
                $io->section("Adding test feeds");
                foreach (self::TEST_FEEDS as $feed) {
                    $this->addFeed(
                        $baseUrl,
                        $feed["url"],
                        $feed["title"],
                        $feed["folder"],
                    );
                    $io->writeln("  Added: " . $feed["title"]);
                }
                $io->success("Feeds added");
            }

            // Set read/seen status for Sven's Blog items only:
            // - First 3 (index 0-2): unread + new (unseen) - default, no action
            // - 5th (index 4): unread + seen
            // - 4th and 6th onwards (index 3, 5+): read + seen
            $svensGuid = $this->feedContentService->createGuid(
                self::TEST_FEEDS[0]["url"],
            );
            $allItems = $this->feedViewService->loadEnrichedItems(
                $user->getId(),
            );
            $svensItems = array_values(
                array_filter(
                    $allItems,
                    fn($item) => $item["sguid"] === $svensGuid,
                ),
            );
            $guids = array_column($svensItems, "guid");

            $itemsToMarkRead = [];
            $itemsToMarkSeen = [];
            foreach ($guids as $index => $guid) {
                if ($index >= 3) {
                    // All from index 3 onwards are seen
                    $itemsToMarkSeen[] = $guid;
                    // All except index 4 are also read
                    if ($index !== 4) {
                        $itemsToMarkRead[] = $guid;
                    }
                }
            }

            if (!empty($itemsToMarkRead)) {
                $this->readStatusService->markManyAsRead(
                    $user->getId(),
                    $itemsToMarkRead,
                );
            }
            if (!empty($itemsToMarkSeen)) {
                $this->seenStatusService->markManyAsSeen(
                    $user->getId(),
                    $itemsToMarkSeen,
                );
            }

            // Capture feed view
            $io->section("Capturing Feed");
            $this->driver->get($baseUrl . "/");
            $this->waitForPage();
            sleep(1); // Let content load

            // Click on "Sven's Blog" subscription in sidebar
            $subscriptionLinks = $this->driver->findElements(
                WebDriverBy::xpath(
                    "//*[contains(@class, 'subscription-list')]//a[contains(text(), \"Sven's Blog\")]",
                ),
            );
            if (count($subscriptionLinks) > 0) {
                $subscriptionLinks[0]->click();
                $this->waitForPage();
                sleep(1);
            }

            // Click on first article to show reading pane
            $articles = $this->driver->findElements(
                WebDriverBy::cssSelector("#feed section > div"),
            );
            if (count($articles) > 0) {
                $articles[0]->click();
                sleep(1);
            }
            $this->takeScreenshot("feed");
            $io->success("feed.png");

            // Capture feed view with light theme
            $io->section("Capturing Feed (Light)");
            $this->driver->executeScript(
                "document.documentElement.setAttribute('data-theme', 'light');",
            );
            usleep(500000);
            $this->takeScreenshot("feed-light");
            $io->success("feed-light.png");

            // Reset to dark theme for remaining screenshots
            $this->driver->executeScript(
                "document.documentElement.setAttribute('data-theme', 'dark');",
            );

            // Capture subscriptions
            $io->section("Capturing Subscriptions");
            $this->driver->get($baseUrl . "/subscriptions");
            $this->waitForPage();
            $this->takeScreenshot("subscriptions");
            $io->success("subscriptions.png");

            // Capture preferences
            $io->section("Capturing Preferences");
            // Create a processed message so "Webhook: Online" is shown
            $this->processedMessageRepository->save(
                new ProcessedMessage(
                    RefreshFeedsMessage::class,
                    ProcessedMessage::STATUS_SUCCESS,
                ),
            );
            $this->driver->get($baseUrl . "/preferences");
            $this->waitForPage();
            $this->takeScreenshot("preferences");
            $io->success("preferences.png");

            // Capture mobile screenshots (iPhone 15 portrait)
            // Restart WebDriver with mobile viewport size
            $this->driver->quit();
            $this->setupWebDriver(
                $chromedriverUrl,
                self::MOBILE_VIEWPORT_WIDTH,
                self::MOBILE_VIEWPORT_HEIGHT,
            );

            // Login again for mobile session
            // Wait for next TOTP window to avoid code reuse issues
            $io->section("Waiting for new TOTP window");
            $this->waitForNextTotpWindow($io);
            $this->login($baseUrl, $totpSecret);

            $io->section("Capturing Mobile Feed List");
            // Navigate directly to Sven's Blog subscription URL
            $svensGuid = $this->feedContentService->createGuid(
                self::TEST_FEEDS[0]["url"],
            );
            $this->driver->get($baseUrl . "/s/" . $svensGuid);
            $this->waitForPage();
            sleep(1);
            $this->takeScreenshot("feed-mobile-list");
            $io->success("feed-mobile-list.png");

            // Capture mobile reading pane - click first article
            $io->section("Capturing Mobile Reading Pane");
            $articles = $this->driver->findElements(
                WebDriverBy::cssSelector("[data-reading-list] > div"),
            );
            if (count($articles) > 0) {
                $articles[0]->click();
                $this->waitForPage();
                sleep(1);
            }
            $this->takeScreenshot("feed-mobile-reading");
            $io->success("feed-mobile-reading.png");

            $io->success("All screenshots captured in " . $screenshotDir);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed: " . $e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->cleanup();
        }
    }

    private function setupWebDriver(
        string $chromedriverUrl,
        int $width = self::VIEWPORT_WIDTH,
        int $height = self::VIEWPORT_HEIGHT,
    ): void {
        $options = new ChromeOptions();
        $args = [
            "--headless=new",
            "--disable-gpu",
            "--no-sandbox",
            "--disable-dev-shm-usage",
            "--force-device-scale-factor=1",
            sprintf("--window-size=%d,%d", $width, $height),
        ];

        $options->addArguments($args);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $this->driver = RemoteWebDriver::create(
            $chromedriverUrl,
            $capabilities,
        );

        // Set window size via WebDriver API
        $this->driver
            ->manage()
            ->window()
            ->setSize(
                new \Facebook\WebDriver\WebDriverDimension($width, $height),
            );
    }

    private function waitForPage(): void
    {
        $this->driver
            ->wait(10)
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName("body"),
                ),
            );
        // Force dark theme
        $this->driver->executeScript(
            "document.documentElement.setAttribute('data-theme', 'dark');",
        );
        usleep(500000); // 500ms for rendering
    }

    private function takeScreenshot(string $name): void
    {
        $path = $this->getScreenshotDir() . "/" . $name . ".png";
        $this->driver->takeScreenshot($path);
    }

    private function getScreenshotDir(): string
    {
        return dirname(__DIR__, 2) . "/" . self::SCREENSHOT_DIR;
    }

    private function createTestUser(string $totpSecret): User
    {
        $user = new User(self::TEST_EMAIL);
        $user->setEmail(self::TEST_EMAIL);
        $user->setTotpSecret($totpSecret);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            self::TEST_PASSWORD,
        );
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function login(string $baseUrl, string $totpSecret): void
    {
        $this->driver->get($baseUrl . "/login");
        $this->waitForPage();

        // Fill login form
        $this->driver
            ->findElement(WebDriverBy::id("login_email"))
            ->sendKeys(self::TEST_EMAIL);
        $this->driver
            ->findElement(WebDriverBy::id("login_password"))
            ->sendKeys(self::TEST_PASSWORD);

        // Generate current OTP
        $totp = TOTP::createFromSecret($totpSecret);
        $otpCode = $totp->now();

        // Fill OTP input (single field)
        $this->driver
            ->findElement(WebDriverBy::cssSelector(".otp-input"))
            ->sendKeys($otpCode);

        // Submit form
        $this->driver
            ->findElement(
                WebDriverBy::cssSelector('form button[type="submit"]'),
            )
            ->click();
        $this->waitForPage();

        // Verify login was successful - we should no longer be on the login page
        $currentUrl = $this->driver->getCurrentURL();
        if (str_contains($currentUrl, "/login")) {
            throw new \RuntimeException(
                "Login failed - still on login page. URL: " . $currentUrl,
            );
        }
    }

    private function addFeed(
        string $baseUrl,
        string $feedUrl,
        string $title,
        ?string $folder = null,
    ): void {
        $this->driver->get($baseUrl . "/subscriptions");
        $this->waitForPage();

        // Find the URL input and add button
        $urlInput = $this->driver->findElement(
            WebDriverBy::cssSelector('input[type="url"]'),
        );
        $urlInput->clear();
        $urlInput->sendKeys($feedUrl);

        // Click subscribe button (first section's submit button)
        $this->driver
            ->findElement(
                WebDriverBy::cssSelector(
                    '.page-section:first-of-type button[type="submit"]',
                ),
            )
            ->click();
        $this->waitForPage();

        // Update the title and folder - find the last form row in manage section
        $formRows = $this->driver->findElements(
            WebDriverBy::cssSelector(".page-section--bordered .form-row"),
        );
        if (count($formRows) > 0) {
            $lastRow = $formRows[count($formRows) - 1];

            // Update name
            $nameInput = $lastRow->findElement(
                WebDriverBy::cssSelector('input[type="text"][id$="_name"]'),
            );
            $nameInput->clear();
            $nameInput->sendKeys($title);

            // Update folder if provided
            if ($folder !== null) {
                $folderInput = $lastRow->findElement(
                    WebDriverBy::cssSelector(
                        'input[type="text"][id$="_folder"]',
                    ),
                );
                $folderInput->clear();
                $folderInput->sendKeys($folder);
            }

            // Save immediately
            $saveButton = $this->driver->findElements(
                WebDriverBy::cssSelector(
                    '.page-section--bordered .form-actions button[type="submit"]',
                ),
            );
            if (count($saveButton) > 0) {
                $saveButton[0]->click();
                $this->waitForPage();
            }
        }
    }

    private function cleanup(): void
    {
        if ($this->driver) {
            $this->driver->quit();
        }
    }

    private function waitForNextTotpWindow(SymfonyStyle $io): void
    {
        // TOTP codes are valid for 30 seconds
        // Wait until we're in a new 30-second window to avoid code reuse
        $currentTime = time();
        $secondsIntoWindow = $currentTime % 30;
        $secondsToWait = 30 - $secondsIntoWindow + 1; // +1 to be safe

        $io->writeln(
            sprintf(
                "  Waiting %d seconds for next TOTP window...",
                $secondsToWait,
            ),
        );
        sleep($secondsToWait);
    }
}
