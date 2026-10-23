<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MemberQuickSearchService
{
    public const MAX_RESULTS = 8;

    public const MAX_RECENT = 10;

    private const COLUMNS = ['id', 'first_name', 'last_name', 'email', 'member_number', 'status'];

    /**
     * Full-text search over name, email and member number. Every whitespace
     * separated term has to match at least one of the columns.
     */
    public function search(int $gymId, string $term): Collection
    {
        $terms = preg_split('/\s+/', mb_strtolower(trim($term)), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($terms)) {
            return collect();
        }

        $query = $this->baseQuery($gymId);

        foreach ($terms as $value) {
            $like = '%'.addcslashes($value, '\\%_').'%';

            $query->where(function (Builder $q) use ($like) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(member_number) LIKE ?', [$like]);
            });
        }

        return $query->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (Member $member) => $this->present($member));
    }

    /**
     * Resolve recently viewed members of the gym, keeping the given order.
     * IDs of other gyms or deleted members are silently dropped.
     *
     * @param  array<int>  $ids
     */
    public function findByIds(int $gymId, array $ids): Collection
    {
        $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, self::MAX_RECENT);

        if (empty($ids)) {
            return collect();
        }

        $members = $this->baseQuery($gymId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->filter(fn (int $id) => $members->has($id))
            ->map(fn (int $id) => $this->present($members->get($id)))
            ->values();
    }

    private function baseQuery(int $gymId): Builder
    {
        return Member::query()
            ->where('gym_id', $gymId)
            ->select(self::COLUMNS);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => trim($member->first_name.' '.$member->last_name),
            'email' => $member->email,
            'member_number' => $member->member_number,
            'status' => $member->status,
        ];
    }
}
