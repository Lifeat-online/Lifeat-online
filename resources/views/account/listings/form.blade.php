@extends('layouts.public')

@section('title', 'Edit Listing | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Edit Listing Profile</h2>
                <p class="muted">Update safe business profile details here. Package status, public visibility, and renewals remain controlled through the listing workspace and checkout flow.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Back to listing workspace</a>
            </div>
        </div>
    </section>

    <section class="section">
        <article class="card">
            @if ($errors->any())
                <div class="empty-state" style="margin-bottom:1rem; color:#b91c1c;">
                    {{ implode(' ', $errors->all()) }}
                </div>
            @endif

            @if (session('status'))
                <div class="empty-state" style="margin-bottom:1rem; color:#166534;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="post" action="{{ route('account.listings.update', $listing) }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                @method('PUT')

                <div>
                    <label for="title">Business name</label>
                    <input id="title" name="title" value="{{ old('title', $listing->title) }}">
                </div>
                <div>
                    <label for="website_url">Website</label>
                    <input id="website_url" name="website_url" value="{{ old('website_url', $listing->website_url) }}">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" value="{{ old('email', $listing->email) }}">
                </div>
                <div>
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $listing->phone) }}">
                </div>
                <div>
                    <label for="address_line">Address</label>
                    <input id="address_line" name="address_line" value="{{ old('address_line', $listing->address_line) }}">
                </div>
                <div>
                    <label for="city">City</label>
                    <input id="city" name="city" value="{{ old('city', $listing->city) }}">
                </div>
                <div>
                    <label for="region">Region</label>
                    <input id="region" name="region" value="{{ old('region', $listing->region) }}">
                </div>
                <div>
                    <label for="country">Country</label>
                    <input id="country" name="country" value="{{ old('country', $listing->country) }}">
                </div>
                <div>
                    <label for="postal_code">Postal code</label>
                    <input id="postal_code" name="postal_code" value="{{ old('postal_code', $listing->postal_code) }}">
                </div>
                <div>
                    <label for="category_ids">Categories</label>
                    <select id="category_ids" name="category_ids[]" multiple size="5">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', $selectedCategoryIds), true))>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column: 1 / -1; margin: 1rem 0;">
                    <label>Map Location</label>
                    <x-location-picker
                        :lat="old('latitude', $listing->latitude)"
                        :lng="old('longitude', $listing->longitude)"
                    />
                </div>

                <div style="grid-column:1 / -1;">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="3">{{ old('excerpt', $listing->excerpt) }}</textarea>
                </div>
                <div style="grid-column:1 / -1;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="8">{{ old('description', $listing->description) }}</textarea>
                </div>

                <div>
                    <label for="featured_image_upload">Featured image</label>
                    <input id="featured_image_upload" name="featured_image_upload" type="file" accept="image/*">
                    @if ($listing->featured_image)
                        <label style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                            <input type="checkbox" name="remove_featured_image" value="1">
                            <span>Remove current featured image</span>
                        </label>
                    @endif
                </div>
                <div>
                    <label for="logo_upload">Logo</label>
                    <input id="logo_upload" name="logo_upload" type="file" accept="image/*">
                    @if ($listing->logo_path)
                        <label style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                            <input type="checkbox" name="remove_logo" value="1">
                            <span>Remove current logo</span>
                        </label>
                    @endif
                </div>

                <div style="grid-column:1 / -1; display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button class="button" type="submit">Save listing profile</button>
                    <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Cancel</a>
                </div>
            </form>
        </article>
    </section>
@endsection
