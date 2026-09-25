<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
    }

    public function test_photo_can_be_saved_without_editing_other_profile_fields_and_seen_after_new_login(): void
    {
        Storage::fake(media_storage_disk());
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'profile_photo' => $this->photo('avatar.png'),
        ])->assertRedirect()->assertSessionHas('status', 'Profile photo saved successfully.');

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk(media_storage_disk())->assertExists($path);

        $this->flushSession();
        auth()->logout();
        $this->actingAs($user->fresh())->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(route('storage.media', ['path' => $path]), false);

        $this->get(route('storage.media', ['path' => $path]))->assertOk();
    }

    public function test_replacing_photo_removes_the_previous_file(): void
    {
        Storage::fake(media_storage_disk());
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $oldPath = $this->photo('old.png')->store('profile-photos', media_storage_disk());
        $user->update(['profile_photo_path' => $oldPath]);

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'profile_photo' => $this->photo('new.png'),
        ])->assertRedirect();

        Storage::disk(media_storage_disk())->assertMissing($oldPath);
        Storage::disk(media_storage_disk())->assertExists($user->fresh()->profile_photo_path);
    }

    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
        );
    }
}
