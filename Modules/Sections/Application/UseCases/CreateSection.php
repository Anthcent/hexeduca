<?php

namespace Modules\Sections\Application\UseCases;

use Modules\Sections\Application\DTOs\CreateSectionData;
use Modules\Sections\Domain\Entities\Section;
use Modules\Sections\Domain\Repositories\SectionRepositoryInterface;
use Modules\Sections\Public\Events\SectionCreated;

final class CreateSection
{
    public function __construct(
        private readonly SectionRepositoryInterface $repository,
    ) {}

    public function handle(CreateSectionData $data): Section
    {
        $section = new Section(id: null, schoolId: $data->schoolId, name: $data->name);
        $section = $this->repository->save($section);

        event(new SectionCreated(
            sectionId: $section->id(),
            schoolId: $section->schoolId(),
            name: $section->name(),
        ));

        return $section;
    }
}
