<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Init\StartupSequence;

use ilContext;
use ILIAS\DI\Container;
use ilSession;
use ilTermsOfServiceAcceptanceStartUpStep;
use ilForcedUserPasswordChangeStartUpStep;
use ilTermsOfServiceWithdrawalStartUpStep;
use ilUserProfileStartUpStep;
use SplQueue;
use ILIAS\LegalDocuments\StartUpStep;
use ILIAS\StaticURL\Builder\StandardURIBuilder;

/**
 * Class StartupSequenceDispatcher
 * @package ILIAS\Init\StartupSequence
 * @author  Michael Jansen <mjansen@databay.de>
 */
class StartUpSequenceDispatcher
{
    private Container $dic;
    /** @var SplQueue|\ILIAS\Init\StartupSequence\StartUpSequenceStep[] */
    private $sequence = [];

    public function __construct(Container $dic)
    {
        $this->dic = $dic;
        $this->initSequence();
    }

    protected function initSequence(): void
    {
        $this->sequence = new SplQueue();
        $this->sequence->push(new StartUpStep(
            $this->dic->ctrl(),
            $this->dic['legalDocuments']
        ));
        $this->sequence->push(new ilUserProfileStartUpStep(
            $this->dic->user(),
            $this->dic->ctrl()
        ));
        $this->sequence->push(new ilForcedUserPasswordChangeStartUpStep(
            $this->dic->user(),
            $this->dic->ctrl(),
            $this->dic->http()->request()
        ));
    }

    protected function storeRequest(): void
    {
        if (!ilSession::get('orig_request_target')) {
            //#16324 don't use the complete REQUEST_URI

            $url = $_SERVER['REQUEST_URI'];
            $short = strpos($url, StandardURIBuilder::SHORT);

            if (preg_match('@/([^/]+\\.php[?/].*)$@', $url, $matches)) {
                $url = $matches[1];
            } elseif ($short !== false) {
                $url = substr($url, $short + 1);
            } else {
                $url = substr($url, strrpos($url, '/') + 1);
            }

            ilSession::set('orig_request_target', $url);
        }
    }

    /**
     * @return bool
     */
    public function dispatch(): bool
    {
        $this->dic->logger()->init()->debug('Started request interception checks ...');

        if (defined('IL_CERT_SSO')) {
            $this->dic->logger()->init()->debug('ApacheAuthentication request. No interception.');
            return false;
        } elseif (!ilContext::supportsRedirects()) {
            $this->dic->logger()->init()->debug('Context does not support redirects. No interception.');
            return false;
        } elseif ($this->dic->ctrl()->isAsynch()) {
            $this->dic->logger()->init()->debug('Async request. No interception.');
            return false;
        } elseif (in_array(basename($_SERVER['PHP_SELF']), array('logout.php'))) {
            $this->dic->logger()->init()->debug('Logout request. No interception.');
            return false;
        } elseif (!$this->dic->user()->getId() || $this->dic->user()->isAnonymous()) {
            $this->dic->logger()->init()->debug('Anonymous request. No interception.');
            return false;
        } elseif (ilSession::get(__CLASS__ . '_passed')) {
            $this->dic->logger()->init()->debug(__CLASS__ . ' already passed in the current user session.');
            return false;
        }

        $this->sequence->rewind();
        while (!$this->sequence->isEmpty()) {
            $step = $this->sequence->shift();

            if ($step->isInFulfillment()) {
                $this->dic->globalScreen()->tool()->context()->current()->addAdditionalData(
                    \ILIAS\Init\StartupSequence\StartUpSequenceLayoutProvider::FORCED_STARTUP_STEP,
                    true
                );
                
                $this->dic->logger()->init()->debug('Step is in fulfillment:' . get_class($step));
                return false;
            }

            if ($step->shouldInterceptRequest()) {
                $this->dic->logger()->init()->debug('Step required adjustment:' . get_class($step));
                if ($step->shouldStoreRequestTarget()) {
                    $this->storeRequest();
                }
                $step->execute();
                return true;
            }
        }

        ilSession::set(__CLASS__ . '_passed', 1);
        return false;
    }
}
