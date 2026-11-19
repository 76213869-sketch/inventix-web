<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $empresa = $user->empresa;
        
        $logoUrl = null;
        if ($empresa) {
            $extensions = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($extensions as $ext) {
                $path = "logos/empresa_{$empresa->id}.{$ext}";
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    $logoUrl = \Illuminate\Support\Facades\Storage::url($path);
                    break;
                }
            }
        }

        $avatarUrl = null;
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($extensions as $ext) {
            $path = "avatars/user_{$user->id}.{$ext}";
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                $avatarUrl = \Illuminate\Support\Facades\Storage::url($path);
                break;
            }
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'empresa' => $empresa,
            'logoUrl' => $logoUrl,
            'avatarUrl' => $avatarUrl,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'avatar.image' => 'El archivo debe ser una imagen válida.',
            'avatar.mimes' => 'La foto de perfil debe estar en formato: jpeg, png, jpg o webp.',
            'avatar.max' => 'La foto de perfil no debe pesar más de 5 MB.',
        ]);

        $user->fill($request->only(['nombre', 'correo']));

        if ($user->isDirty('correo')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $extension = $avatar->getClientOriginalExtension();
            
            // Delete old avatars to avoid conflict
            $extensions = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($extensions as $ext) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete("avatars/user_{$user->id}.{$ext}");
            }
            
            // Store new avatar
            $avatar->storeAs('avatars', "user_{$user->id}.{$extension}", 'public');
        }

        return Redirect::route('profile.edit')->with('success', 'Perfil actualizado con éxito.');
    }

    /**
     * Update the company information and logo.
     */
    public function updateEmpresa(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;
        if (!$empresa) {
            return Redirect::route('profile.edit')->with('error', 'No tienes una empresa asociada.');
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'nombre.required' => 'El nombre de la empresa es obligatorio.',
            'correo.email' => 'Ingrese un correo electrónico válido.',
            'logo.image' => 'El archivo debe ser una imagen válida.',
            'logo.mimes' => 'El logotipo debe estar en formato: jpeg, png, jpg o webp.',
            'logo.max' => 'El logotipo no debe pesar más de 5 MB.',
        ]);

        $data = $request->only(['nombre', 'ruc', 'direccion', 'telefono', 'correo']);
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $data[$key] = '';
            }
        }

        $empresa->update($data);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $extension = $logo->getClientOriginalExtension();
            
            // Delete old logo files to avoid conflict
            $extensions = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($extensions as $ext) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete("logos/empresa_{$empresa->id}.{$ext}");
            }
            
            // Store new logo
            $logo->storeAs('logos', "empresa_{$empresa->id}.{$extension}", 'public');
        }

        return Redirect::route('profile.edit')->with('success', 'Configuración de la empresa actualizada con éxito.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
