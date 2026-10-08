<?php

declare(strict_types=1);

namespace Axyr\Productive\Resources;

use Axyr\Productive\Data\Input\CreateTimeEntryData;
use Axyr\Productive\Data\Input\UpdateTimeEntryData;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Pagination\ModelCollection;
use Axyr\Productive\Query\Query;

/**
 * Time entries: time a person spent on a service, optionally on a task.
 *
 * @see https://developer.productive.io/reference/resources/time-entries
 * @see https://developer.productive.io/reference/resources/time-entries-bulk
 */
final class TimeEntryResource extends Resource
{
    protected const string TYPE = 'time_entries';

    protected const string PATH = 'time_entries';

    /**
     * GET /time_entries
     *
     * @return PendingQuery<TimeEntry>
     */
    public function query(): PendingQuery
    {
        return $this->newQuery(TimeEntry::class, 'time_entries.index');
    }

    /**
     * GET /time_entries/{id}
     *
     * @param  list<string>  $include
     */
    public function find(int|string $id, array $include = []): TimeEntry
    {
        return $this->fetchOne(TimeEntry::class, $this->path($id), 'time_entries.show', (new Query())->include(...$include));
    }

    /**
     * POST /time_entries
     *
     * @param  CreateTimeEntryData|array<string, mixed>  $data
     */
    public function create(CreateTimeEntryData|array $data): TimeEntry
    {
        return $this->write(TimeEntry::class, Method::Post, $this->path(), 'time_entries.create', $data);
    }

    /**
     * PATCH /time_entries/{id}
     *
     * @param  UpdateTimeEntryData|array<string, mixed>  $data
     */
    public function update(int|string $id, UpdateTimeEntryData|array $data): TimeEntry
    {
        return $this->write(TimeEntry::class, Method::Patch, $this->path($id), 'time_entries.update', $data, (string) $id);
    }

    /**
     * DELETE /time_entries/{id}
     */
    public function delete(int|string $id): void
    {
        $this->writeWithoutResponse(Method::Delete, $this->path($id), 'time_entries.destroy');
    }

    /**
     * PATCH /time_entries/{id}/approve
     */
    public function approve(int|string $id): TimeEntry
    {
        return $this->write(TimeEntry::class, Method::Patch, $this->path($id, 'approve'), 'time_entries.approve', null);
    }

    /**
     * PATCH /time_entries/{id}/unapprove
     */
    public function unapprove(int|string $id): TimeEntry
    {
        return $this->write(TimeEntry::class, Method::Patch, $this->path($id, 'unapprove'), 'time_entries.unapprove', null);
    }

    /**
     * PATCH /time_entries/{id}/reject
     */
    public function reject(int|string $id): TimeEntry
    {
        return $this->write(TimeEntry::class, Method::Patch, $this->path($id, 'reject'), 'time_entries.reject', null);
    }

    /**
     * PATCH /time_entries/{id}/unreject
     */
    public function unreject(int|string $id): TimeEntry
    {
        return $this->write(TimeEntry::class, Method::Patch, $this->path($id, 'unreject'), 'time_entries.unreject', null);
    }

    /**
     * POST /time_entries (bulk)
     *
     * @param  list<CreateTimeEntryData|array<string, mixed>>  $entries
     * @return ModelCollection<TimeEntry>
     */
    public function bulkCreate(array $entries): ModelCollection
    {
        $items = array_map(fn(CreateTimeEntryData|array $entry): array => ['attributes' => self::attributes($entry)], $entries);

        /** @var ModelCollection<TimeEntry> */
        return $this->bulkWrite(Method::Post, 'time_entries.create_bulk', $items);
    }

    /**
     * PATCH /time_entries (bulk)
     *
     * @param  array<int|string, UpdateTimeEntryData|array<string, mixed>>  $updates  Keyed by time entry ID.
     * @return ModelCollection<TimeEntry>
     */
    public function bulkUpdate(array $updates): ModelCollection
    {
        $items = [];

        foreach ($updates as $id => $update) {
            $items[] = ['id' => (string) $id, 'attributes' => self::attributes($update)];
        }

        /** @var ModelCollection<TimeEntry> */
        return $this->bulkWrite(Method::Patch, 'time_entries.update_bulk', $items);
    }

    /**
     * DELETE /time_entries (bulk)
     *
     * @param  list<int|string>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        $this->bulkWithoutResponse(Method::Delete, $this->path(), 'time_entries.destroy_bulk', $ids);
    }

    /**
     * PATCH /time_entries/approve (bulk)
     *
     * @param  list<int|string>  $ids
     */
    public function bulkApprove(array $ids): void
    {
        $this->bulkWithoutResponse(Method::Patch, $this->path('approve'), 'time_entries.approve_bulk', $ids);
    }

    /**
     * PATCH /time_entries/unapprove (bulk)
     *
     * @param  list<int|string>  $ids
     */
    public function bulkUnapprove(array $ids): void
    {
        $this->bulkWithoutResponse(Method::Patch, $this->path('unapprove'), 'time_entries.unapprove_bulk', $ids);
    }
}
