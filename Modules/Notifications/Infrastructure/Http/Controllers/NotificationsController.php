<?php

namespace Modules\Notifications\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Notifications\Application\DTOs\SendAnnouncementData;
use Modules\Notifications\Application\UseCases\MarkAllNotificationsAsRead;
use Modules\Notifications\Application\UseCases\MarkNotificationAsRead;
use Modules\Notifications\Application\UseCases\SendAnnouncement;
use Modules\Notifications\Infrastructure\Http\Requests\StoreAnnouncementRequest;
use Modules\Notifications\Infrastructure\Models\SchoolNotification;

class NotificationsController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $notifications = SchoolNotification::query()
            ->forRecipient($request->user()->id, $tenantContext->current()->id)
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (SchoolNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'senderName' => $notification->sender_name,
                'createdAt' => $notification->created_at?->toIso8601String(),
                'readAt' => $notification->read_at?->toIso8601String(),
            ]);

        return Inertia::render('Notifications::Index', [
            'inbox' => $notifications,
            'canSend' => $request->user()->can('notifications.send'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Notifications::Create');
    }

    public function store(StoreAnnouncementRequest $request, SendAnnouncement $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $sent = $useCase->handle(new SendAnnouncementData(
            schoolId: $tenantContext->current()->id,
            senderId: $request->user()->id,
            senderName: (string) $request->user()->name,
            title: $request->string('title')->toString(),
            body: $request->string('body')->toString(),
            audience: $request->string('audience')->toString(),
        ));

        return redirect()->route('notifications.index')
            ->with('success', $sent === 1 ? 'Anuncio enviado a 1 destinatario.' : "Anuncio enviado a {$sent} destinatarios.");
    }

    public function markAsRead(Request $request, int $notification, MarkNotificationAsRead $useCase, TenantContext $tenantContext): RedirectResponse
    {
        if (! $useCase->handle($notification, $request->user()->id, $tenantContext->current()->id)) {
            abort(404);
        }

        return back();
    }

    public function markAllAsRead(Request $request, MarkAllNotificationsAsRead $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $useCase->handle($request->user()->id, $tenantContext->current()->id);

        return back()->with('success', 'Todas las notificaciones quedaron marcadas como leídas.');
    }
}
