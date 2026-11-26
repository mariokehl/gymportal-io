<?php

namespace App\Console\Commands;

use App\Models\Gym;
use App\Models\GymApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Issues, lists and revokes Agent API tokens.
 *
 *   php artisan agent:token create --gym=my-gym --name="Assistant" \
 *       --purpose="Answers member questions" --abilities=read
 *   php artisan agent:token list --gym=my-gym
 *   php artisan agent:token revoke --id=3
 */
class AgentTokenCommand extends Command
{
    protected $signature = 'agent:token
        {action : create, list or revoke}
        {--gym= : Gym id or slug}
        {--name= : Name of the client, e.g. "Assistant"}
        {--purpose= : What the client is for (required on create)}
        {--abilities=read : "read", "all" or a comma separated list}
        {--expires= : Expiry date, e.g. 2027-01-31}
        {--id= : Client id (for revoke)}';

    protected $description = 'Manage Agent API tokens of a gym';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'create' => $this->create(),
            'list' => $this->list(),
            'revoke' => $this->revoke(),
            default => $this->invalid(),
        };
    }

    private function create(): int
    {
        $gym = $this->resolveGym();

        if (! $gym) {
            return self::FAILURE;
        }

        $name = (string) $this->option('name');
        $purpose = (string) $this->option('purpose');

        if ($name === '' || $purpose === '') {
            $this->error('--name and --purpose are required.');

            return self::FAILURE;
        }

        $abilities = match ($this->option('abilities')) {
            'read' => GymApiClient::READ_ABILITIES,
            'all' => GymApiClient::ABILITIES,
            default => array_values(array_filter(array_map('trim', explode(',', (string) $this->option('abilities'))))),
        };

        try {
            [$client, $token] = GymApiClient::issue(
                $gym,
                $name,
                $purpose,
                $abilities,
                $this->option('expires') ? Carbon::parse($this->option('expires'))->endOfDay() : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Client #'.$client->id.' for gym "'.$gym->name.'" created.');
        $this->line('Abilities: '.implode(', ', $client->abilities));
        $this->newLine();
        $this->warn('Token (shown once, store it now):');
        $this->line($token);

        return self::SUCCESS;
    }

    private function list(): int
    {
        $gym = $this->resolveGym();

        if (! $gym) {
            return self::FAILURE;
        }

        $rows = GymApiClient::where('gym_id', $gym->id)->orderBy('id')->get()->map(fn (GymApiClient $c) => [
            $c->id,
            $c->name,
            $c->token_prefix.'…',
            implode(', ', $c->abilities ?? []),
            $c->revoked_at ? 'revoked' : ($c->isUsable() ? 'active' : 'expired'),
            optional($c->last_used_at)->toDateTimeString() ?? '-',
        ]);

        $this->table(['ID', 'Name', 'Token', 'Abilities', 'State', 'Last used'], $rows);

        return self::SUCCESS;
    }

    private function revoke(): int
    {
        $client = GymApiClient::find($this->option('id'));

        if (! $client) {
            $this->error('No client with this --id.');

            return self::FAILURE;
        }

        $client->revoke();
        $this->info('Client #'.$client->id.' ("'.$client->name.'") revoked.');

        return self::SUCCESS;
    }

    private function resolveGym(): ?Gym
    {
        $key = $this->option('gym');

        $gym = $key === null ? null : (ctype_digit((string) $key)
            ? Gym::find((int) $key)
            : Gym::where('slug', $key)->first());

        if (! $gym) {
            $this->error('Gym not found. Pass --gym=<id or slug>.');
        }

        return $gym;
    }

    private function invalid(): int
    {
        $this->error('Action must be create, list or revoke.');

        return self::FAILURE;
    }
}
