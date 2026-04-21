<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\elements\User;
use craft\web\Controller;
use craft\web\Request;
use yii\web\Response;

/**
 * Front-end affiliate registration. Anonymous visitors create a Craft user
 * and a pending affiliate in one submit; protected by a honeypot and a
 * per-IP rate limit, and respects Craft's email verification flow.
 */
class RegistrationController extends Controller
{
    private const REGISTRATION_RATE_LIMIT = 10;
    private const REGISTRATION_RATE_WINDOW_SECONDS = 3600;

    protected array|bool|int $allowAnonymous = ['form', 'register'];

    public function actionForm(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $plugin = KickBack::getInstance();

        if ($user !== null && $plugin->affiliates->isAffiliate($user->id)) {
            return $this->redirect('/' . $plugin->getSettings()->getCurrentSitePortalPath());
        }

        $recruiter = (string)(Craft::$app->getRequest()->getQueryParam('recruiter') ?? '');

        if ($recruiter !== '') {
            $parentAffiliate = $plugin->affiliates->getAffiliateByReferralCode($recruiter);
            if ($parentAffiliate === null || $parentAffiliate->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
                Craft::$app->getSession()->setNotice(Craft::t(
                    'kickback',
                    "We couldn't find that referral code, but you can still sign up.",
                ));
                $recruiter = '';
            }
        }

        return $this->renderTemplate('kickback/portal/register', [
            'programs' => $plugin->programs->getAllPrograms(),
            'currentUser' => $user,
            'recruiterCode' => $recruiter,
        ]);
    }

    public function actionRegister(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $plugin = KickBack::getInstance();

        // Honeypot - silently redirect bots.
        if (!empty($request->getBodyParam('website'))) {
            Craft::warning('Affiliate registration honeypot triggered from IP ' . $request->userIP, __METHOD__);
            return $this->redirect('/');
        }

        if (!$this->checkRegistrationRateLimit($request->userIP)) {
            Craft::warning("Affiliate registration rate limit hit for IP {$request->userIP}", __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Too many registration attempts. Please try again later.'));
            return null;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser !== null && $plugin->affiliates->isAffiliate($currentUser->id)) {
            return $this->redirect('/' . $plugin->getSettings()->getCurrentSitePortalPath());
        }

        if ($currentUser === null) {
            $currentUser = $this->createUser($request);
            if ($currentUser === null) {
                // Null with an error flash = real validation failure.
                // Null without one = enumeration guard hit; fall through to the
                // generic "check your email" response so the account status leaks nothing.
                if (Craft::$app->getSession()->hasFlash('error')) {
                    return null;
                }
                if ($request->getAcceptsJson()) {
                    return $this->asJson(['success' => true, 'status' => 'pending_verification']);
                }
                Craft::$app->getSession()->setNotice(Craft::t(
                    'kickback',
                    'Account created! Check your email to verify and activate your account.',
                ));
                return $this->redirect('/');
            }
        }

        $programId = (int)$request->getBodyParam('programId');
        if ($programId === 0) {
            $programId = $plugin->programs->getDefaultProgram()?->id ?? 0;
        }

        if ($programId === 0) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'registration.noProgram'));
            return null;
        }

        $attributes = [
            'paypalEmail' => $request->getBodyParam('paypalEmail'),
            'payoutMethod' => $request->getBodyParam('payoutMethod'),
            'notes' => $request->getBodyParam('notes'),
        ];

        $parentWarning = null;
        $parentCode = $request->getBodyParam('parentReferralCode');
        if (!empty($parentCode)) {
            $parentAffiliate = $plugin->affiliates->getAffiliateByReferralCode($parentCode);
            if ($parentAffiliate === null) {
                Craft::warning("Registration with unknown parent referral code: {$parentCode}", __METHOD__);
            } elseif (AffiliateElement::resolveParentStatus($parentAffiliate->affiliateStatus) === 'inactive') {
                Craft::warning("Registration with inactive parent affiliate #{$parentAffiliate->id} (status: {$parentAffiliate->affiliateStatus})", __METHOD__);
                $parentWarning = Craft::t(
                    'kickback',
                    'The referral link you used is no longer active. You have been registered without a recruiter.',
                );
                Craft::$app->getSession()->setNotice($parentWarning);
            } else {
                $attributes['parentAffiliateId'] = $parentAffiliate->id;
            }
        }

        $affiliate = $plugin->affiliates->registerAffiliate($currentUser, $programId, $attributes);

        if ($affiliate === null) {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'errors' => ['registration' => [Craft::t('kickback', 'registration.message.failed')]]]);
            }
            Craft::$app->getSession()->setError(Craft::t('kickback', 'registration.message.failed'));
            return null;
        }

        if ($currentUser->pending) {
            if ($request->getAcceptsJson()) {
                $response = ['success' => true, 'status' => 'pending_verification'];
                if ($parentWarning !== null) {
                    $response['warning'] = $parentWarning;
                }
                return $this->asJson($response);
            }
            Craft::$app->getSession()->setNotice(Craft::t(
                'kickback',
                'Account created! Check your email to verify and activate your account.',
            ));
            return $this->redirect('/');
        }

        if (Craft::$app->getUser()->getIsGuest()) {
            Craft::$app->getUser()->login($currentUser);
        }

        if ($request->getAcceptsJson()) {
            $response = ['success' => true, 'status' => $affiliate->affiliateStatus];
            if ($parentWarning !== null) {
                $response['warning'] = $parentWarning;
            }
            return $this->asJson($response);
        }

        $portalPath = '/' . $plugin->getSettings()->getCurrentSitePortalPath();

        if ($affiliate->affiliateStatus === AffiliateElement::STATUS_PENDING) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', 'registration.message.submitted'));
            return $this->redirect($portalPath . '/pending');
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'registration.message.welcome'));
        return $this->redirect($portalPath);
    }

    /**
     * Sliding per-IP cache counter. True = allowed.
     */
    private function checkRegistrationRateLimit(string $ip): bool
    {
        $cache = Craft::$app->getCache();
        $key = 'kickback_registration_throttle:' . sha1($ip);

        $attempts = (int)$cache->get($key);
        if ($attempts >= self::REGISTRATION_RATE_LIMIT) {
            return false;
        }

        $cache->set($key, $attempts + 1, self::REGISTRATION_RATE_WINDOW_SECONDS);
        return true;
    }

    /**
     * Create a Craft user from the posted fields. Returns null on validation
     * failure (error flash set) OR on collision with an existing email
     * (silent - the caller produces the generic success response to prevent
     * account enumeration). Existing users are never emailed.
     */
    private function createUser(Request $request): ?User
    {
        $email = trim((string)$request->getBodyParam('email'));
        $firstName = trim((string)$request->getBodyParam('firstName'));
        $lastName = trim((string)$request->getBodyParam('lastName'));
        $password = (string)$request->getBodyParam('password');

        if ($email === '' || $password === '') {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Email and password are required.'));
            return null;
        }

        if (Craft::$app->getUsers()->getUserByUsernameOrEmail($email) !== null) {
            Craft::info('Affiliate registration attempted for existing email (enumeration guard hit)', __METHOD__);
            return null;
        }

        $user = new User();
        $user->username = $email;
        $user->email = $email;
        $user->firstName = $firstName !== '' ? $firstName : null;
        $user->lastName = $lastName !== '' ? $lastName : null;
        $user->newPassword = $password;

        if (!Craft::$app->getElements()->saveElement($user)) {
            $firstError = current($user->getFirstErrors()) ?: null;
            Craft::$app->getSession()->setError($firstError ?? Craft::t('kickback', "Couldn't create user account."));
            return null;
        }

        return $user;
    }
}
