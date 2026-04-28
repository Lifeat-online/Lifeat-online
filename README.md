# Life Platform

A modern, interactive community directory and event platform built with Laravel. This platform enables local businesses to manage their presence and promote events through a geo-aware interactive map interface.

## 🚀 Key Features

- **Interactive Directory**: Browse local businesses on a high-performance interactive map (Leaflet.js + OpenStreetMap).
- **Event Management**: Specialized event discovery with proximity-based searching and fallback geolocation logic.
- **Owner Dashboard**: Intuitive admin interface for business owners to manage listings, pick exact map locations, and track campaign performance.
- **Geo-Aware Search**: "Near Me" functionality using browser geolocation to filter results by physical distance.
- **Marker Clustering**: Optimized map performance for high-density areas.
- **Premium Design**: Modern, responsive UI with full dark mode support and sleek animations.

## 🛠 Tech Stack

- **Backend**: Laravel 11.x (PHP 8.2+)
- **Frontend**: Blade, Vanilla CSS, JavaScript
- **Mapping**: Leaflet.js, OpenStreetMap
- **Database**: SQLite (Local Dev) / MySQL compatible (Production)
- **Styling**: Modern CSS with CSS Variables for theme consistency

## 📦 Installation

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd life-platform
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Setup Database**:
   ```bash
   touch database/database.sqlite
   php artisan migrate --seed
   ```

5. **Run Development Server**:
   ```bash
   php artisan serve
   npm run dev
   ```

## 🗺 Interactive Mapping Logic

The platform uses a custom Blade component `x-map-embed` that handles both single-pin (details pages) and multi-marker (index pages) modes. It includes built-in support for:
- Dark mode tile filtering.
- Automatic clustering for performance.
- Distance calculation badges in popups.
- Fallback coordinates (Event → Parent Listing).

## 📄 License

Open-source software licensed under the [MIT license](LICENSE).
