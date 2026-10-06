<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isExplicitlyAuditedReservationUpdate = $request->routeIs(
            'admin.reservations.update',
            'admin.reservations.accept',
            'admin.reservations.complete',
            'admin.reservations.cancel',
            'admin.reservations.payments.details',
        );
        $isBackupAction = $request->routeIs('admin.backups*');
        $isExplicitlyAuditedAdminManagement = $request->routeIs('admin.users.store', 'admin.users.update-name', 'admin.users.status');

        if (($request->is('admin/*') || $request->is('admin')) && $request->session()->get('is_admin') && ! $request->isMethod('GET') && ! $isExplicitlyAuditedReservationUpdate && ! $isBackupAction && ! $isExplicitlyAuditedAdminManagement) {
            ActivityLog::create([
                'user_id' => $request->session()->get('admin_user_id'),
                'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
                'actor_email' => $request->session()->get('admin_email'),
                'actor_role' => $request->session()->get('admin_role', 'limited'),
                'action' => $this->actionLabel($request),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => $this->description($request),
            ]);
        }

        return $response;
    }

    private function actionLabel(Request $request): string
    {
        return match ($request->route()?->getName()) {
            'admin.inquiries.reply' => 'Replied to inquiry',
            'admin.inquiries.destroy' => 'Deleted inquiry',
            'admin.backups.upload' => 'Uploaded backup',
            'admin.backups.restore' => 'Restored backup',
            'admin.backups.delete' => 'Deleted backup',
            'admin.packages.store' => 'Created package',
            'admin.packages.update' => 'Updated package',
            'admin.packages.destroy' => 'Deleted package',
            'admin.gallery.store' => 'Added gallery item',
            'admin.gallery.update' => 'Updated gallery item',
            'admin.gallery.destroy' => 'Deleted gallery item',
            default => 'Performed admin action',
        };
    }

    private function description(Request $request): string
    {
        $routeName = $request->route()?->getName();

        return match ($routeName) {
            'admin.inquiries.reply' => 'Sent an email reply for inquiry #' . $request->route('inquiry')?->id . ' and marked it Responded.',
            'admin.inquiries.destroy' => 'Deleted inquiry #' . $request->route('inquiry')?->id . '.',
            'admin.backups.upload' => 'Uploaded backup file “' . basename((string) $request->file('backup_file')?->getClientOriginalName()) . '”.',
            'admin.backups.restore' => 'Restored backup “' . $request->input('backup') . '”.',
            'admin.backups.delete' => 'Deleted backup “' . $request->input('backup') . '”.',
            'admin.packages.store' => 'Created package “' . $request->input('name') . '”.',
            'admin.packages.update' => 'Updated package “' . $request->route('package')?->name . '”: ' . $this->changedFields($request, ['name', 'price', 'description', 'menu', 'freebies', 'addons', 'event_type', 'is_featured']) . '.',
            'admin.packages.destroy' => 'Deleted package “' . $request->route('package')?->name . '”.',
            'admin.gallery.store' => 'Added gallery item “' . $request->input('title') . '”.',
            'admin.gallery.update' => 'Updated gallery item “' . $request->route('gallery')?->title . '”: ' . $this->changedFields($request, ['title', 'event_type', 'description', 'image', 'is_featured']) . '.',
            'admin.gallery.destroy' => 'Deleted gallery item “' . $request->route('gallery')?->title . '”.',
            default => $request->method() . ' ' . $request->path(),
        };
    }

    private function changedFields(Request $request, array $fields): string
    {
        $changed = collect($fields)->filter(fn ($field) => $request->has($field))->map(fn ($field) => str($field)->replace('_', ' '))->implode(', ');

        return $changed ? 'changed ' . $changed : 'saved changes';
    }
}
