<?php

namespace App\Libraries\Sms;

final class WorkflowSmsPolicy
{
    public const TABLES = ['vendors', 'gate_pass_requests', 'ptw_applications', 'tenders',
        'tender_bids', 'tender_communications', 'tender_requests'];

    public static function event(string $table, array $before, array $after): ?array
    {
        if (!empty($after['deleted'])) { return null; }
        $status = (string) ($after['status'] ?? '');
        $oldStatus = (string) ($before['status'] ?? '');
        $changed = $status !== $oldStatus;
        $stage = (string) ($after['stage'] ?? '');
        if ($table === 'vendors' && $changed && in_array($status, ['approved', 'rejected', 'revise', 'blocked'], true)) {
            return ['module' => 'vendor', 'reason' => $status];
        }
        if ($table === 'gate_pass_requests' && $changed
            && in_array($status, ['department_approved', 'commercial_approved', 'security_approved', 'rop_approved', 'returned', 'rejected', 'cancelled'], true)) {
            return ['module' => 'gate_pass', 'reason' => $status];
        }
        if ($table === 'ptw_applications') {
            if ($changed && in_array($status, ['revise', 'rejected', 'approved', 'issued', 'completed', 'cancelled'], true)) {
                return ['module' => 'ptw', 'reason' => $status];
            }
            if ($before && $stage !== ($before['stage'] ?? '')
                && in_array($stage, ['hmo', 'terminal', 'issued', 'completed'], true)) {
                return ['module' => 'ptw', 'reason' => $stage === 'issued' ? 'issued' : 'stage_approved'];
            }
        }
        if ($table === 'tenders' && $changed && in_array($status, ['awarded', 'cancelled'], true)) {
            return ['module' => 'tender', 'reason' => $status];
        }
        if ($table === 'tender_bids' && $changed && in_array($status, ['accepted', 'rejected'], true)) {
            return ['module' => 'tender', 'reason' => 'technical_' . $status];
        }
        if ($table === 'tender_communications' && !empty($after['is_vendor_visible'])
            && $status === 'published' && ($changed || !$before)) {
            return ['module' => 'tender', 'reason' => 'message'];
        }
        if ($table === 'tender_requests' && $changed && in_array($status, ['manager_approved', 'finance_verified', 'rejected', 'revise'], true)) {
            return ['module' => 'tender', 'reason' => $status];
        }
        return null;
    }

    public static function message(string $module, string $reference, string $reason, int $language): string
    {
        $en = ['approved' => 'approved', 'rejected' => 'rejected', 'revise' => 'requires revision',
            'returned' => 'requires revision', 'blocked' => 'blocked', 'cancelled' => 'cancelled',
            'department_approved' => 'approved by the department; review continues',
            'commercial_approved' => 'commercial review completed; security review is next',
            'security_approved' => 'approved by Security; ROP review is next', 'rop_approved' => 'issued',
            'stage_approved' => 'approved at the current stage; review continues', 'issued' => 'issued',
            'completed' => 'completed', 'awarded' => 'awarded to you', 'not_awarded' => 'awarded to another vendor',
            'technical_accepted' => 'technically accepted; evaluation continues', 'technical_rejected' => 'technically rejected',
            'message' => 'has a new message or request for information', 'manager_approved' => 'approved by the department manager',
            'finance_verified' => 'verified by Finance'];
        $ar = ['approved' => 'تمت الموافقة', 'rejected' => 'تم الرفض', 'revise' => 'مطلوب تعديل',
            'returned' => 'مطلوب تعديل', 'blocked' => 'تم الحظر', 'cancelled' => 'تم الإلغاء',
            'department_approved' => 'تمت موافقة القسم والمراجعة مستمرة',
            'commercial_approved' => 'اكتملت المراجعة التجارية والطلب لدى الأمن',
            'security_approved' => 'تمت موافقة الأمن والطلب لدى الشرطة', 'rop_approved' => 'تم الإصدار',
            'stage_approved' => 'تمت موافقة المرحلة والمراجعة مستمرة', 'issued' => 'تم الإصدار',
            'completed' => 'تم الإكمال', 'awarded' => 'تمت الترسية لصالحكم', 'not_awarded' => 'تمت الترسية لمورد آخر',
            'technical_accepted' => 'تم القبول الفني والتقييم مستمر', 'technical_rejected' => 'تم الرفض الفني',
            'message' => 'توجد رسالة جديدة أو طلب معلومات', 'manager_approved' => 'تمت موافقة مدير القسم',
            'finance_verified' => 'تم التحقق من المالية'];
        $reference = mb_substr(preg_replace('/[\r\n\t]/', ' ', $reference), 0, 55);
        if ($language === 64) {
            $label = ['vendor' => 'المورد', 'gate_pass' => 'تصريح الدخول', 'ptw' => 'تصريح العمل', 'tender' => 'المناقصة'][$module];
            return 'ميناء الدقم: ' . $label . ' ' . $reference . ': ' . ($ar[$reason] ?? 'تم التحديث') . '. يرجى تسجيل الدخول للاطلاع على التفاصيل والإجراء المطلوب.';
        }
        $reference = preg_replace('/[^\x20-\x7E]/', '', $reference);
        $label = ['vendor' => 'Vendor', 'gate_pass' => 'Gate pass', 'ptw' => 'PTW', 'tender' => 'Tender'][$module];
        return 'Port of Duqm: ' . $label . ' ' . $reference . ' ' . ($en[$reason] ?? 'updated') . '. Sign in to view details and any action required.';
    }
}
