<?php

namespace Tests\Feature;

use App\Enums\GroupRole;
use App\Enums\RaceDriverStatus;
use App\Models\Driver;
use App\Models\DriverImport;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Race;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DriverImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Group $group, string $role = GroupRole::Admin->value): User
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->for(Profile::factory()->create(['user_id' => $user->id]))->create();
        $group->members()->attach($driver->id, ['role' => $role, 'availability' => 'available', 'joined_at' => now()]);

        return $user->refresh();
    }

    private function groupDriver(Group $group, string $name, string $nickname = 'XX'): Driver
    {
        $driver = Driver::factory()->for(Profile::factory()->create(['full_name' => $name]))->create([
            'nickname' => $nickname,
        ]);
        $group->members()->attach($driver->id, ['role' => GroupRole::Member->value, 'availability' => 'available', 'joined_at' => now()]);

        return $driver;
    }

    /**
     * Drivers in the group, excluding the creator Group::factory() makes for us,
     * which no import is allowed to touch.
     */
    private function memberDriverCount(Group $group): int
    {
        return Driver::whereHas('groups', fn ($q) => $q->where('groups.id', $group->id))->count();
    }

    private function upload(User $user, Group $group, string $csv, string $name = 'drivers.csv'): TestResponse
    {
        return $this->actingAs($user)->post(
            route('groups.imports.store', $group),
            ['group_id' => $group->id, 'file' => UploadedFile::fake()->createWithContent($name, $csv)],
        );
    }

    private function confirm(User $user, Group $group, DriverImport $import, array $actions): TestResponse
    {
        return $this->actingAs($user)->post(
            route('groups.imports.confirm', ['group' => $group, 'import' => $import]),
            ['rows' => $actions],
        );
    }

    public function test_uploading_builds_a_preview_and_writes_no_drivers(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $before = Driver::count();

        $this->upload($user, $group, "Name,Event,Position,Points\nBilal Kazi,PITSTOP,1,25\nBrand New Person,VIRAJ,2,18\n")
            ->assertRedirect();

        $this->assertSame($before, Driver::count(), 'uploading must not create drivers');
        $this->assertSame(0, Profile::where('full_name', 'Bilal Kazi')->count());

        $import = DriverImport::firstOrFail();
        $this->assertSame(DriverImport::PREVIEW, $import->status);
        $this->assertNull($import->confirmed_at);
        $this->assertSame(2, $import->rows()->count());
    }

    public function test_an_exact_name_match_is_flagged_and_never_overwritten(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $existing = $this->groupDriver($group, 'Shoaib Khan', 'SK');
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name\nShoaib Khan\n")->assertRedirect();

        $import = DriverImport::firstOrFail();
        $row = $import->rows()->firstOrFail();
        $this->assertSame('exact', $row->match_status);
        $this->assertSame($existing->id, $row->matched_driver_id);
        $this->assertNull($row->created_driver_id);

        $this->confirm($user, $group, $import, [$row->row_number => 'link'])->assertRedirect();

        // Linking writes a pointer on the import row and nothing on the driver.
        $existing->refresh();
        $this->assertSame('Shoaib Khan', $existing->profile->full_name);
        $this->assertSame('SK', $existing->nickname);
        $this->assertSame($existing->id, $row->fresh()->matched_driver_id);
        $this->assertSame($before, $this->memberDriverCount($group));
    }

    /**
     * A "Last, First" cell has to be quoted to be one cell in a real CSV, and
     * once it is, it still resolves to the same person.
     */
    public function test_a_quoted_last_first_name_matches_the_same_person(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $existing = $this->groupDriver($group, 'Zahid Khan');

        $this->upload($user, $group, "name\n\"Khan, Zahid\"\n")->assertRedirect();

        $row = DriverImport::firstOrFail()->rows()->firstOrFail();
        $this->assertSame('exact', $row->match_status);
        $this->assertSame($existing->id, $row->matched_driver_id);
    }

    public function test_an_unquoted_comma_is_read_as_a_separator_not_a_name(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name\nKhan, Zahid\n")->assertRedirect();

        $row = DriverImport::firstOrFail()->rows()->firstOrFail();
        $this->assertSame('Khan', $row->raw_name, 'the cell before the comma is the name cell');
    }

    /**
     * Three-token names put a one-token mismatch in the 0.6 to 1.0 band, which is
     * the "similar but not certain" case that must reach a person.
     */
    public function test_a_close_but_inexact_name_is_held_for_manual_review(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $this->groupDriver($group, 'Zahid Khan Pathan');
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name\nZahid Kahn Pathan\n")->assertRedirect();

        $row = DriverImport::firstOrFail()->rows()->firstOrFail();
        $this->assertSame('uncertain', $row->match_status);
        $this->assertTrue($row->needsReview());
        $this->assertGreaterThanOrEqual(0.6, $row->match_score);
        $this->assertLessThan(1.0, $row->match_score);
        $this->assertStringContainsString('Confirm before importing', implode(' ', $row->issues));

        $this->assertSame($before, $this->memberDriverCount($group));
    }

    public function test_an_uncertain_row_cannot_be_confirmed_without_an_explicit_decision(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $this->groupDriver($group, 'Zahid Khan Pathan');

        $this->upload($user, $group, "name\nZahid Kahn Pathan\n")->assertRedirect();
        $import = DriverImport::firstOrFail();
        $before = $this->memberDriverCount($group);

        // No decision submitted: held back rather than applied by omission.
        $this->actingAs($user)
            ->from(route('groups.imports.show', ['group' => $group, 'import' => $import]))
            ->post(route('groups.imports.confirm', ['group' => $group, 'import' => $import]))
            ->assertRedirect()
            ->assertSessionHasErrors('rows');

        $this->assertSame(DriverImport::PREVIEW, $import->fresh()->status);
        $this->assertSame($before, $this->memberDriverCount($group));
    }

    /**
     * Two existing drivers whose names fold to the same tokens are genuinely
     * indistinguishable, so the row must ask rather than guess.
     */
    public function test_ambiguous_matches_are_flagged_and_blocked(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $this->groupDriver($group, 'Shoaib Khan', 'SK');
        $this->groupDriver($group, 'Khan, Shoaib', 'KS');

        $this->upload($user, $group, "name\nShoaib Khan\n")->assertRedirect();

        $import = DriverImport::firstOrFail();
        $row = $import->rows()->firstOrFail();
        $this->assertSame('ambiguous', $row->match_status);
        $this->assertTrue($row->needsReview());

        $before = $this->memberDriverCount($group);

        $this->actingAs($user)
            ->from(route('groups.imports.show', ['group' => $group, 'import' => $import]))
            ->post(route('groups.imports.confirm', ['group' => $group, 'import' => $import]))
            ->assertSessionHasErrors('rows');

        $this->assertSame(DriverImport::PREVIEW, $import->fresh()->status);
        $this->assertNull($row->fresh()->applied_at);
        $this->assertSame($before, $this->memberDriverCount($group));
    }

    public function test_ambiguous_row_can_be_resolved_by_linking_to_a_named_driver(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $this->groupDriver($group, 'Shoaib Khan', 'SK');
        $chosen = $this->groupDriver($group, 'Khan, Shoaib', 'KS');

        $this->upload($user, $group, "name\nShoaib Khan\n")->assertRedirect();
        $import = DriverImport::firstOrFail();
        $row = $import->rows()->first();
        $before = $this->memberDriverCount($group);

        $this->confirm($user, $group, $import, [$row->row_number => 'link:'.$chosen->id])->assertRedirect();

        $this->assertSame(DriverImport::APPLIED, $import->fresh()->status);
        $this->assertSame($chosen->id, $row->fresh()->matched_driver_id);
        $this->assertNotNull($row->fresh()->applied_at);
        $this->assertSame($before, $this->memberDriverCount($group), 'linking must not create a driver');
    }

    public function test_a_duplicate_name_inside_the_file_is_flagged(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name\nAyaz Chaus\nAyaz Chaus\n")->assertRedirect();

        $import = DriverImport::firstOrFail();
        $rows = $import->rows;
        $this->assertCount(2, $rows);
        $this->assertSame('duplicate', $rows[0]->match_status);
        $this->assertSame('duplicate', $rows[1]->match_status);

        $this->confirm($user, $group, $import, [
            $rows[0]->row_number => 'skip',
            $rows[1]->row_number => 'skip',
        ])->assertRedirect();

        $this->assertSame(1, $this->memberDriverCount($group), 'a duplicate pair must not create two drivers');
    }

    public function test_a_new_driver_is_created_only_on_confirmation(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name,event,position,points\nNew Driver,PITSTOP,1,25\n")->assertRedirect();

        $import = DriverImport::firstOrFail();
        $row = $import->rows()->first();
        $this->assertSame('unmatched', $row->match_status);
        $this->assertSame($before, $this->memberDriverCount($group));

        $this->confirm($user, $group, $import, [$row->row_number => 'create'])->assertRedirect();

        $this->assertSame(DriverImport::APPLIED, $import->fresh()->status);

        $created = Driver::with('profile')->whereHas('profile', fn ($q) => $q->where('full_name', 'New Driver'))->first();
        $this->assertNotNull($created, 'the confirmed row creates a driver');
        $this->assertTrue($group->members()->where('driver_id', $created->id)->exists());
        $this->assertSame($before + 1, $this->memberDriverCount($group));
    }

    /**
     * The schema is users 1:1 profiles 1:1 drivers, so a new identity needs a
     * login row. It must not be sign-in capable and must not carry a real
     * address from the CSV.
     */
    public function test_a_created_driver_gets_an_unusable_placeholder_account(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name,email\nReal Person,real.person@example.com\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->confirm($user, $group, $import, [$import->rows()->first()->row_number => 'create'])->assertRedirect();

        $driver = Driver::with(['profile.user'])->whereHas('profile', fn ($q) => $q->where('full_name', 'Real Person'))->firstOrFail();

        $this->assertNotNull($driver->profile->user);
        $this->assertStringEndsWith('@imported.invalid', $driver->profile->user->email);
        $this->assertNotSame('real.person@example.com', $driver->profile->user->email);
        $this->assertStringNotContainsString('real.person', $driver->profile->user->email);
    }

    public function test_confirming_twice_does_not_create_duplicate_drivers(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name\nRepeat Person\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->confirm($user, $group, $import, [$import->rows()->first()->row_number => 'create'])->assertRedirect();
        $this->assertSame($before + 1, $this->memberDriverCount($group));

        $this->confirm($user, $group, $import, [$import->rows()->first()->row_number => 'create'])
            ->assertStatus(422);

        $this->assertSame($before + 1, $this->memberDriverCount($group));
    }

    public function test_a_name_that_appears_between_preview_and_confirmation_is_not_duplicated(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name\nLate Arrival\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        // Somebody with this name is added to the group by another route after
        // the preview was built.
        $late = $this->groupDriver($group, 'Late Arrival', 'LA');

        $this->confirm($user, $group, $import, [$import->rows()->first()->row_number => 'create'])->assertRedirect();

        $row = $import->rows()->first();
        $this->assertNull($row->created_driver_id, 'no second driver is created');
        $this->assertSame('duplicate', $row->match_status);
        $this->assertSame(1, Driver::whereHas('profile', fn ($q) => $q->where('full_name', 'Late Arrival'))->count());
        $this->assertNotNull($late);
    }

    public function test_import_never_writes_race_results(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $race = Race::factory()->create(['group_id' => $group->id, 'status' => 'completed']);
        $season = Season::create(['group_id' => $group->id, 'name' => 'Season', 'status' => 'active']);
        $season->races()->attach($race->id, ['round_number' => 1]);

        $driver = $this->groupDriver($group, 'Historic Driver');
        $race->entries()->create([
            'driver_id' => $driver->id, 'kart_number' => 3, 'status' => 'finished',
            'confirmed' => true, 'ready' => true, 'finish_position' => 1,
        ]);

        $entryBefore = $race->entries()->first()->toArray();

        $this->upload($user, $group, "name,event,position,points\nHistoric Driver,PITSTOP,1,25\nBrand New,FNF,1,25\n")->assertRedirect();

        $import = DriverImport::firstOrFail();
        $this->confirm($user, $group, $import, collect($import->rows)->mapWithKeys(fn ($r) => [$r->row_number => 'create'])->all())
            ->assertRedirect();

        // The historical entry is unchanged, and the CSV's claimed position and
        // points were not applied anywhere.
        $this->assertEquals($entryBefore, $race->entries()->first()->toArray());
        $this->assertSame(1, $race->entries()->count());
        $this->assertSame(1, $race->entries()->first()->finish_position);
        $this->assertSame(RaceDriverStatus::Finished, $race->entries()->first()->status);
    }

    public function test_import_cannot_touch_another_group(): void
    {
        $group = Group::factory()->create();
        $other = Group::factory()->create();
        $user = $this->admin($other);

        $this->upload($user, $other, "name\nSomeone New\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->actingAs($user)
            ->get(route('groups.imports.show', ['group' => $group, 'import' => $import]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('groups.imports.confirm', ['group' => $group, 'import' => $import]))
            ->assertForbidden();
    }

    public function test_ordinary_member_cannot_use_the_import(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group, GroupRole::Member->value);

        $this->actingAs($user)->get(route('groups.imports.index', $group))->assertForbidden();

        $this->actingAs($user)
            ->post(route('groups.imports.store', $group), [
                'group_id' => $group->id,
                'file' => UploadedFile::fake()->createWithContent('drivers.csv', "name\nX\n"),
            ])
            ->assertForbidden();

        $this->assertSame(0, DriverImport::count());
    }

    public function test_organizer_can_use_the_import(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group, GroupRole::Organizer->value);

        $this->actingAs($user)->get(route('groups.imports.index', $group))->assertOk();
    }

    public function test_a_file_without_a_name_column_is_rejected(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "kart,time\n1,41.2\n")->assertSessionHasErrors('file');

        $this->assertSame(0, DriverImport::count());
        $this->assertSame($before, $this->memberDriverCount($group));
    }

    public function test_the_import_tab_is_offered_to_organisers_only(): void
    {
        $group = Group::factory()->create();
        $organiser = $this->admin($group, GroupRole::Organizer->value);

        $member = User::factory()->create();
        $memberDriver = Driver::factory()->for(Profile::factory()->create(['user_id' => $member->id]))->create();
        $group->members()->attach($memberDriver->id, [
            'role' => GroupRole::Member->value,
            'availability' => 'available',
            'joined_at' => now(),
        ]);

        $organiserTab = $this->actingAs($organiser)->get(route('groups.show', $group));
        $organiserTab->assertOk()->assertSee('Import');

        $memberTab = $this->actingAs($member->refresh())->get(route('groups.show', $group));
        $memberTab->assertOk()->assertDontSee(route('groups.imports.index', $group), false);
    }

    public function test_the_import_tab_stays_highlighted_on_the_review_screen(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name\nBilal Kazi\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $response = $this->actingAs($user)->get(
            route('groups.imports.show', ['group' => $group, 'import' => $import])
        );

        $response->assertOk();
        $response->assertSee('aria-current', false);
    }

    public function test_a_header_only_file_is_rejected(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name,event\n")->assertSessionHasErrors('file');

        $this->assertSame(0, DriverImport::count());
    }

    public function test_rows_with_bad_data_are_reported_not_imported(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, "name,email,position\n,not-an-email,abc\n")->assertRedirect();

        $row = DriverImport::firstOrFail()->rows()->firstOrFail();
        $this->assertSame('invalid', $row->match_status);
        $this->assertStringContainsString('No driver name', implode(' ', $row->issues));
        $this->assertStringContainsString('Finish position', implode(' ', $row->issues));
        $this->assertStringContainsString('valid address', implode(' ', $row->issues));
    }

    public function test_an_invalid_row_cannot_be_created(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name,email\n,not-an-email\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        // A hand-crafted "create" for an invalid row must not slip through.
        $this->confirm($user, $group, $import, [$import->rows()->first()->row_number => 'create'])
            ->assertStatus(422);

        $this->assertSame($before, $this->memberDriverCount($group));
    }

    /**
     * A usable name does not make bad data importable. Each of these rows has
     * a real name, so the row is matched on name alone, but the malformed
     * value still has to stop the row short of being created.
     */
    public function test_bad_data_on_a_named_row_is_invalid_not_merely_unmatched(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $this->upload($user, $group, implode("\n", [
            'name,email,position',
            'Bad Email Driver,not-an-email,1',
            'Bad Position Driver,,0',
            'Text Position Driver,,first',
            'Perfectly Fine Driver,,3',
        ]))->assertRedirect();

        $rows = DriverImport::firstOrFail()->rows()->orderBy('row_number')->get();

        $this->assertSame(
            ['invalid', 'invalid', 'invalid', 'unmatched'],
            $rows->pluck('match_status')->all(),
        );

        $allIssues = $rows->map(fn ($row) => implode(' ', $row->issues))->implode(' ');

        $this->assertStringContainsString('valid address', $allIssues);
        $this->assertStringContainsString('Finish position', $allIssues);
    }

    public function test_a_named_row_with_bad_data_cannot_be_forced_into_existence(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name,email\nForced Bad Row,not-an-email\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->confirm($user, $group, $import, [$import->rows()->first()->row_number => 'create'])
            ->assertStatus(422);

        $this->assertSame($before, $this->memberDriverCount($group));
    }

    /**
     * Bad data is also fatal on a row that would otherwise match an existing
     * driver exactly, so a typo in the file cannot quietly ride along on a
     * link that only touches the staging table.
     */
    public function test_bad_data_is_invalid_even_when_the_name_matches_exactly(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $existing = $this->groupDriver($group, 'Khalid Rahman');

        $this->upload($user, $group, "name,email\nKhalid Rahman,nope\n")->assertRedirect();
        $row = DriverImport::firstOrFail()->rows()->firstOrFail();

        $this->assertSame('invalid', $row->match_status);
        $this->assertStringContainsString('valid address', implode(' ', $row->issues));

        $this->assertDatabaseHas('drivers', ['id' => $existing->id, 'profile_id' => $existing->profile_id]);
    }

    public function test_a_discarded_import_creates_nothing(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $before = $this->memberDriverCount($group);

        $this->upload($user, $group, "name\nNever Imported\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->actingAs($user)
            ->delete(route('groups.imports.destroy', ['group' => $group, 'import' => $import]))
            ->assertRedirect();

        $this->assertSame(0, DriverImport::count());
        $this->assertSame($before, $this->memberDriverCount($group));
        $this->assertSame(0, Profile::where('full_name', 'Never Imported')->count());
    }

    public function test_existing_teams_are_untouched_by_an_import(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);

        $a = $this->groupDriver($group, 'Team Driver A', 'TA');
        $b = $this->groupDriver($group, 'Team Driver B', 'TB');

        $team = Team::create(['group_id' => $group->id, 'name' => 'Apex Racing', 'logo_initials' => 'AR']);
        $team->members()->attach([$a->id, $b->id]);

        $membershipBefore = $team->members()->pluck('drivers.id')->sort()->values()->all();
        $membersBefore = $this->memberDriverCount($group);

        $this->upload($user, $group, "name\nTeam Driver A\nBrand New Person\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->confirm($user, $group, $import, collect($import->rows)->mapWithKeys(fn ($r) => [$r->row_number => 'create'])->all())
            ->assertRedirect();

        $this->assertSame($membershipBefore, $team->members()->pluck('drivers.id')->sort()->values()->all());
        $this->assertSame($membersBefore + 1, $this->memberDriverCount($group));
    }

    public function test_the_preview_page_explains_that_nothing_is_written_yet(): void
    {
        $group = Group::factory()->create();
        $user = $this->admin($group);
        $this->groupDriver($group, 'Shoaib Khan', 'SK');
        $this->groupDriver($group, 'Khan, Shoaib', 'KS');

        $this->upload($user, $group, "name\nShoaib Khan\n")->assertRedirect();
        $import = DriverImport::firstOrFail();

        $this->actingAs($user)
            ->get(route('groups.imports.show', ['group' => $group, 'import' => $import]))
            ->assertOk()
            ->assertSee('Nothing has been imported');
    }
}
