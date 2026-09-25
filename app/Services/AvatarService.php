<?php

namespace App\Services;

use App\Http\Requests\Portal\ProfileRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Profile photos: center-cropped to a square and resized to 512 px (WebP when available,
 * else JPEG), stored on the public disk. The previous photo is deleted.
 */
class AvatarService
{
    public const int SIZE = 512;

    public function store(User $user, UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'Image illisible : envoyez une photo JPG, PNG ou WebP.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        $webp = function_exists('imagewebp');
        ob_start();
        $webp ? imagewebp($square, null, 82) : imagejpeg($square, null, 85);
        $binary = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($square);

        $disk = config('media.disk');
        $path = 'avatars/'.$user->id.'-'.Str::random(8).($webp ? '.webp' : '.jpg');
        Storage::disk($disk)->put($path, $binary, ['ContentType' => $webp ? 'image/webp' : 'image/jpeg']);

        $this->delete($user);
        $user->forceFill(['avatar_path' => $path, 'avatar_disk' => $disk])->save();

        return $path;
    }

    /**
     * Name, email (not for a back-office account: it is its login), city and photo.
     */
    public function updateProfile(User $user, ProfileRequest $request): User
    {
        $user->fill([
            'name' => $request->validated('name'),
            'city_id' => $request->validated('city_id'),
            'commune_id' => $request->validated('commune_id'),
        ]);
        if ($user->organizerMemberships()->doesntExist()) {
            $user->email = $request->validated('email');
        }
        $user->save();

        if ($request->hasFile('photo')) {
            $this->store($user, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $this->delete($user);
        }

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        if ($user->avatar_path) {
            rescue(fn () => Storage::disk($user->avatar_disk ?? 'public')->delete($user->avatar_path), report: false);
            $user->forceFill(['avatar_path' => null, 'avatar_disk' => null])->save();
        }
    }
}
