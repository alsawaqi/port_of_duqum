<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Enforce the application verb allowlist and prevent state-changing
 * controller actions from being invoked with GET/HEAD.
 *
 * The legacy RISE router exposes controller methods through a broad dynamic
 * route. CSRF validation does not apply to safe HTTP verbs, so mutations must
 * be rejected before controller dispatch even when an individual controller
 * forgot to perform its own method check.
 */
class SafeHttpMethods implements FilterInterface
{
    private const MUTATING_ACTION = '/^(?:'
        . 'add|create|save|update|delete|remove|destroy|'
        . 'approve|reject|accept|decline|return|restore|archive|unarchive|'
        . 'activate|deactivate|enable|disable|block|unblock|'
        . 'pay|refund|charge|submit|send|resend|upload|import|'
        . 'install|uninstall|assign|unassign|issue|revoke|renew|'
        . 'start|stop|toggle|set|mark|record|clear|reset|move|copy|sync|'
        . 'process|execute|finalize|publish|unpublish|do|sign_out|logout|'
        . 'apply_leave|article_helpful_status|award|bitbucket|bulk|cancel_tender|'
        . 'change_cart_item_quantity|change_estimate_request_status|change_status|'
        . 'download_updates|duplicate_request|estimate_form_field_delete|'
        . 'fee_waiver_decision|github|like_comment|link_client_to_ticket|pin_comment|'
        . 'place_order|provision_vendor_contact_access|reply|request_clarification|'
        . 'request_my_account_removal|request_revision|retender|review|sign_opening|'
        . 'snooze_reminder|stripe_payment|stripe_subscription|select_vendor|switch_vendor'
        . ')(?:_|$)/i';

    public function before(RequestInterface $request, $arguments = null)
    {
        $segments = $request->getUri()->getSegments();
        $action = strtolower((string) ($segments[1] ?? ''));
        if ($action !== '' && str_starts_with($action, '_')) {
            return Services::response()
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => 'The requested action was not found.',
                ]);
        }

        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'HEAD', 'POST', 'OPTIONS'], true)) {
            return Services::response()
                ->setStatusCode(405)
                ->setHeader('Allow', 'GET, HEAD, POST, OPTIONS')
                ->setJSON([
                    'success' => false,
                    'message' => 'This HTTP method is not allowed.',
                ]);
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return null;
        }

        $isApprovalRequest = (bool) preg_match(
            '/^request_.*(?:approval|decision|access|change|review)(?:_|$)/i',
            $action
        );

        if ($action !== '' && (preg_match(self::MUTATING_ACTION, $action) || $isApprovalRequest)) {
            return Services::response()
                ->setStatusCode(405)
                ->setHeader('Allow', 'POST')
                ->setJSON([
                    'success' => false,
                    'message' => 'This action requires a POST request.',
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
