{{-- Profile page side column: the account at a glance, and tips for keeping it safe. --}}
<div class="fi-profile-side">
    <div class="fi-admin-card">
        <div class="fi-admin-card-head">
            <span class="fi-admin-card-title">
                <x-filament::icon icon="heroicon-o-identification" class="fi-profile-card-icon" />
                Account overview
            </span>
        </div>
        <dl class="fi-profile-facts">
            <div><dt>Role</dt><dd>{{ $role }}</dd></div>
            <div><dt>{{ $workspaceLabel }}</dt><dd>{{ $workspace }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $phone ?: 'Not added' }}</dd></div>
            <div>
                <dt>Status</dt>
                <dd><span @class(['fi-profile-status', 'is-active' => $active])><span class="fi-profile-dot"></span>{{ $active ? 'Active' : 'Inactive' }}</span></dd>
            </div>
            @if ($memberSince)<div><dt>Member since</dt><dd>{{ $memberSince }}</dd></div>@endif
            @if ($updated)<div><dt>Last updated</dt><dd>{{ $updated }}</dd></div>@endif
        </dl>
    </div>

    <div class="fi-admin-card">
        <div class="fi-admin-card-head">
            <span class="fi-admin-card-title">
                <x-filament::icon icon="heroicon-o-light-bulb" class="fi-profile-card-icon" />
                Keep your account secure
            </span>
        </div>
        <ul class="fi-profile-tips">
            <li>Use at least 8 characters, with a mix of letters, numbers and symbols.</li>
            <li>Don't reuse a password from another website.</li>
            <li>Never share your sign-in details. Add each team member as their own staff account.</li>
        </ul>
    </div>
</div>
