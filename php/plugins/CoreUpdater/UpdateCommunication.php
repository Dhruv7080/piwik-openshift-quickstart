<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreUpdater;

use Piwik\Config;
use Piwik\Mail;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\SettingsPiwik;
use Piwik\UpdateCheck;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;

/**
 * Class to check and notify users via email if there is a core update available.
 */
class UpdateCommunication
{

    /**
     * Checks whether update communciation in general is enabled or not.
     *
     * @return bool
     */
    public function isEnabled()
    {
        $isEnabled = Config::getInstance()->General['enable_update_communication'];

        return !empty($isEnabled);
    }

    /**
     * Sends a notification email to all super users if there is a core update available but only if we haven't notfied
     * them about a specific new version yet.
     *
     * @return bool
     */
    public function sendNotificationIfUpdateAvailable()
    {
        if (!$this->isNewVersionAvailable()) {
            return;
        }

        if ($this->hasNotificationAlreadyReceived()) {
            return;
        }

        $this->setHasLatestUpdateNotificationReceived();
        $this->sendNotifications();
    }

    protected function sendNotifications()
    {
        $latestVersion = $this->getLatestVersion();

        $host = SettingsPiwik::getPiwikUrl();

        $subject  = Piwik::translate('CoreUpdater_NotificationSubjectAvailableCoreUpdate', $latestVersion);
        $message  = Piwik::translate('ScheduledReports_EmailHello');
        $message .= "\n\n";
        $message .= Piwik::translate('CoreUpdater_ThereIsNewVersionAvailableForUpdate');
        $message .= "\n\n";
        $message .= Piwik::translate('CoreUpdater_YouCanUpgradeAutomaticallyOrDownloadPackage', $latestVersion);
        $message .= "\n\n";
        $message .= $host . 'index.php?module=CoreUpdater&action=newVersionAvailable';
        $message .= "\n\n";
        $message .= Piwik::translate('CoreUpdater_FeedbackRequest');
        $message .= "\n";
        $message .= 'http://piwik.org/contact/';

        $this->sendEmailNotification($subject, $message);
    }

    private function isVersionLike($latestVersion)
    {
        return strlen($latestVersion) < 18;
    }

    /**
     * Send an email notification to all super users.
     *
     * @param $subject
     * @param $message
     */
    protected function sendEmailNotification($subject, $message)
    {
        $superUsers = UsersManagerApi::getInstance()->getUsersHavingSuperUserAccess();

        foreach ($superUsers as $superUser) {
            $mail = new Mail();
            $mail->setDefaultFromPiwik();
            $mail->addTo($superUser['email']);
            $mail->setSubject($subject);
            $mail->setBodyText($message);
            $mail->send();
        }
    }

    private function isNewVersionAvailable()
    {
        UpdateCheck::check();

        $hasUpdate = UpdateCheck::isNewestVersionAvailable();

        if (!$hasUpdate) {
            return false;
        }

        $latestVersion = self::getLatestVersion();
        if (!$this->isVersionLike($latestVersion)) {
            return false;
        }

        return $hasUpdate;
    }

    private function hasNotificationAlreadyReceived()
    {
        $latestVersion   = $this->getLatestVersion();
        $lastVersionSent = $this->getLatestVersionSent();

        if (!empty($lastVersionSent)
            && ($latestVersion == $lastVersionSent
                || version_compare($latestVersion, $lastVersionSent) == -1)) {
            return true;
        }

        return false;
    }

    private function getLatestVersion()
    {
        return UpdateCheck::getLatestVersion();
    }

    private function getLatestVersionSent()
    {
        return Option::get($this->getNotificationSentOptionName());
    }

    private function setHasLatestUpdateNotificationReceived()
    {
        $latestVersion = $this->getLatestVersion();

        Option::set($this->getNotificationSentOptionName(), $latestVersion);
    }

    private function getNotificationSentOptionName()
    {
        return 'last_update_communication_sent_core';
    }
}
