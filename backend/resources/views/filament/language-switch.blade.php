{{-- Language switch on sign-in and other simple pages (staff choose English or Arabic). --}}
@php($isArabic = app()->getLocale() === 'ar')
<div style="display:flex;justify-content:center;margin-top:1rem">
    <a href="{{ route('admin.language', $isArabic ? 'en' : 'ar') }}"
       lang="{{ $isArabic ? 'en' : 'ar' }}"
       style="color:#C7FF3D;font-weight:600;font-size:.875rem;text-decoration:none;padding:.5rem .75rem;border-radius:.5rem">
        {{ $isArabic ? 'English' : 'العربية' }}
    </a>
</div>
