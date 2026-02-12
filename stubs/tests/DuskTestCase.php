<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Create the RemoteWebDriver instance.
     * Connects to the Selenium standalone-chrome container in DDEV.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ]);

        return RemoteWebDriver::create(
            'http://selenium:4444/wd/hub',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            ),
        );
    }

    /**
     * Login as the seeded admin user.
     * Handles the case where the browser may already be authenticated.
     */
    protected function loginAsAdmin(Browser $browser): Browser
    {
        $browser->visit('/admin/login')->pause(1000);

        $currentUrl = $browser->driver->getCurrentURL();

        // If already authenticated (redirected to dashboard), just go to /admin
        if (! str_contains($currentUrl, '/admin/login')) {
            return $browser->visit('/admin');
        }

        return $browser->waitFor('#form\\.email', 10)
            ->type('#form\\.email', 'admin@aicl.test')
            ->type('#form\\.password', 'password')
            ->press('Sign in')
            ->waitForLocation('/admin', 15);
    }

    /**
     * Ensure the browser is in an unauthenticated state.
     */
    protected function ensureLoggedOut(Browser $browser): Browser
    {
        // Navigate to a page first to establish cookies context
        $browser->visit('/admin/login')->pause(500);

        $currentUrl = $browser->driver->getCurrentURL();

        // If we're on the login page, we're already logged out
        if (str_contains($currentUrl, '/admin/login')) {
            return $browser;
        }

        // We're authenticated — log out via Dusk's built-in logout route
        $browser->visit('/_dusk/logout/web');
        $browser->pause(1000);

        return $browser;
    }
}
