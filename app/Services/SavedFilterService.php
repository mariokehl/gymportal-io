<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\SavedFilter;
use App\Models\User;
use App\Services\Filtering\FilterDefinition;
use App\Services\Filtering\FilterField;
use Illuminate\Validation\ValidationException;

/**
 * Named filters of the filter builder, shared by the staff of a gym.
 */
class SavedFilterService
{
    public const CONTEXTS = [SavedFilter::CONTEXT_MEMBERS];

    public const MAX_PER_CONTEXT = 50;

    public function __construct(private readonly MemberFilterFields $memberFilterFields) {}

    public function forContext(Gym $gym, string $context): array
    {
        return SavedFilter::query()
            ->where('gym_id', $gym->id)
            ->where('context', $context)
            ->orderBy('name')
            ->get()
            ->map(fn (SavedFilter $savedFilter) => $savedFilter->toListArray())
            ->all();
    }

    /**
     * Saves the filter under its name; an existing filter with the same name
     * (ignoring case) is overwritten, like "Speichern unter" in an editor.
     */
    public function save(Gym $gym, User $user, string $context, string $name, array $definition): SavedFilter
    {
        $existing = SavedFilter::query()
            ->where('gym_id', $gym->id)
            ->where('context', $context)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $this->update($existing, $name, $definition);
        }

        $count = SavedFilter::query()->where('gym_id', $gym->id)->where('context', $context)->count();

        if ($count >= self::MAX_PER_CONTEXT) {
            throw ValidationException::withMessages([
                'name' => 'Es können höchstens '.self::MAX_PER_CONTEXT.' Filter gespeichert werden. Bitte löschen Sie zuerst einen Filter.',
            ]);
        }

        return SavedFilter::create([
            'gym_id' => $gym->id,
            'user_id' => $user->id,
            'context' => $context,
            'name' => $name,
            'definition' => $this->sanitize($gym, $context, $definition),
        ]);
    }

    public function update(SavedFilter $savedFilter, string $name, array $definition): SavedFilter
    {
        $savedFilter->update([
            'name' => $name,
            'definition' => $this->sanitize($savedFilter->gym, $savedFilter->context, $definition),
        ]);

        return $savedFilter;
    }

    private function sanitize(Gym $gym, string $context, array $definition): array
    {
        return FilterDefinition::fromArray($definition, $this->fields($gym, $context))->toArray();
    }

    /**
     * @return array<string, FilterField>
     */
    private function fields(Gym $gym, string $context): array
    {
        return match ($context) {
            SavedFilter::CONTEXT_MEMBERS => $this->memberFilterFields->forGym($gym),
        };
    }
}
