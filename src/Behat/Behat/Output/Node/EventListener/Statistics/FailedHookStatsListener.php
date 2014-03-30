<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Behat\Output\Node\EventListener\Statistics;

use Behat\Behat\Output\Statistics\FailedHookStat;
use Behat\Behat\Output\Statistics\Statistics;
use Behat\Testwork\Call\CallResult;
use Behat\Testwork\EventDispatcher\Event\AfterTested;
use Behat\Testwork\EventDispatcher\Event\BeforeTested;
use Behat\Testwork\Exception\ExceptionPresenter;
use Behat\Testwork\Hook\Tester\Setup\HookedSetup;
use Behat\Testwork\Hook\Tester\Setup\HookedTeardown;
use Behat\Testwork\Output\Formatter;
use Behat\Testwork\Output\Node\EventListener\EventListener;
use Symfony\Component\EventDispatcher\Event;

/**
 * Listens and records failed hook stats.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
final class FailedHookStatsListener implements EventListener
{
    /**
     * @var Statistics
     */
    private $statistics;
    /**
     * @var ExceptionPresenter
     */
    private $exceptionPresenter;

    /**
     * Initializes listener.
     *
     * @param Statistics         $statistics
     * @param ExceptionPresenter $exceptionPresenter
     */
    public function __construct(Statistics $statistics, ExceptionPresenter $exceptionPresenter)
    {
        $this->statistics = $statistics;
        $this->exceptionPresenter = $exceptionPresenter;
    }

    /**
     * {@inheritdoc}
     */
    public function listenEvent(Formatter $formatter, Event $event, $eventName)
    {
        $this->captureHookStatsOnEvent($event);
    }

    /**
     * Captures hook stats on hooked event.
     *
     * @param Event $event
     */
    private function captureHookStatsOnEvent(Event $event)
    {
        if ($event instanceof BeforeTested && $event->getSetup() instanceof HookedSetup) {
            $this->captureBeforeHookStats($event->getSetup());
        }

        if ($event instanceof AfterTested && $event->getTeardown() instanceof HookedTeardown) {
            $this->captureAfterHookStats($event->getTeardown());
        }
    }

    /**
     * Captures before hook stats.
     *
     * @param HookedSetup $setup
     */
    private function captureBeforeHookStats(HookedSetup $setup)
    {
        $hookCallResults = $setup->getHookCallResults();

        if (!$hookCallResults->hasExceptions()) {
            return;
        }

        foreach ($hookCallResults as $hookCallResult) {
            $this->captureHookStat($hookCallResult);
        }
    }

    /**
     * Captures before hook stats.
     *
     * @param HookedTeardown $teardown
     */
    private function captureAfterHookStats(HookedTeardown $teardown)
    {
        $hookCallResults = $teardown->getHookCallResults();

        if (!$hookCallResults->hasExceptions()) {
            return;
        }

        foreach ($hookCallResults as $hookCallResult) {
            $this->captureHookStat($hookCallResult);
        }
    }

    /**
     * Captures hook call result.
     *
     * @param CallResult $hookCallResult
     */
    private function captureHookStat(CallResult $hookCallResult)
    {
        if (!$hookCallResult->hasException()) {
            return;
        }

        $callee = $hookCallResult->getCall()->getCallee();
        $hook = (string)$callee;
        $path = $callee->getPath();
        $stdOut = $hookCallResult->getStdOut();
        $error = $hookCallResult->getException()
            ? $this->exceptionPresenter->presentException($hookCallResult->getException())
            : null;

        $stat = new FailedHookStat($hook, $path, $error, $stdOut);
        $this->statistics->registerHookStat($stat);
    }
}