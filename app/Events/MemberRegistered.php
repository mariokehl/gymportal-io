<?php

namespace App\Events;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Gym;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Member $member,
        public Membership $membership,
        public Gym $gym,
        public string $registrationSource = 'unknown',
        public array $additionalData = []
    ) {}
}
