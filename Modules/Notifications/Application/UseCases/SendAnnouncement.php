<?php

namespace Modules\Notifications\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Notifications\Application\DTOs\SendAnnouncementData;
use Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use Modules\Notifications\Domain\ValueObjects\Announcement;
use Modules\Notifications\Domain\ValueObjects\Audience;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\Contracts\TeacherReader;
use Modules\Users\Public\DTOs\StudentDTO;
use Modules\Users\Public\DTOs\TeacherDTO;

final class SendAnnouncement
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly TeacherReader $teachers,
        private readonly StudentReader $students,
    ) {}

    /**
     * Stores one notification per recipient of the audience, all in one
     * transaction, and returns how many were sent.
     */
    public function handle(SendAnnouncementData $data): int
    {
        $announcement = new Announcement($data->title, $data->body);
        $audience = Audience::fromValue($data->audience);

        $notifications = $announcement->deliverTo(
            $this->recipientIds($audience, $data->schoolId),
            $data->schoolId,
            $data->senderId,
            $data->senderName,
        );

        DB::transaction(fn () => $this->notifications->saveMany($notifications));

        return count($notifications);
    }

    /**
     * @return list<int>
     */
    private function recipientIds(Audience $audience, int $schoolId): array
    {
        $ids = [];

        if ($audience->includesTeachers()) {
            $ids = array_map(fn (TeacherDTO $teacher): int => $teacher->id, $this->teachers->allForSchool($schoolId));
        }

        if ($audience->includesStudents()) {
            $ids = [...$ids, ...array_map(fn (StudentDTO $student): int => $student->id, $this->students->allForSchool($schoolId))];
        }

        return $ids;
    }
}
