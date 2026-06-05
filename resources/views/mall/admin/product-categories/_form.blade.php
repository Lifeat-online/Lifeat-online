@csrf
@if ($category->exists) @method('PUT') @endif

<div class="mall-grid">
    <label>Store
        <select class="mall-select" name="mall_store_id" required>
            <option value="">Choose a store</option>
            @foreach ($stores as $store)
                <option value="{{ $store->id }}" @selected((string) old('mall_store_id', $category->mall_store_id) === (string) $store->id)>{{ $store->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Name
        <input class="mall-input" name="name" value="{{ old('name', $category->name) }}" maxlength="100" required>
    </label>
    <label>Sort order
        <input class="mall-input" type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" required>
    </label>
</div>

@if ($category->exists)
    <p class="mall-muted" style="margin:0;">Current slug: {{ $category->slug }}. Renaming the category refreshes its store-scoped slug.</p>
@endif

@if ($errors->any()) <div class="mall-alert">{{ $errors->first() }}</div> @endif

<button class="mall-button" type="submit">{{ $category->exists ? 'Save Category' : 'Create Category' }}</button>
