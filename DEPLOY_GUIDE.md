# Cloud Deployment Guide: Render & Railway

This application is built with a multi-stage Docker setup containing:
- **PHP 8.2 + Apache Web Server** (Public web shop, admin panel, REST APIs)
- **Built React 19 + Vite POS Application** (Served at `/pos`)
- **PostgreSQL Database** (Automated migrations via `sql/migrate.php`)

---

## Option 1: Deploy on Render (Recommended Blueprint)

Render allows 1-click deployment using the included [`render.yaml`](file:///c:/Food%20Application/flavour-pos/render.yaml).

### Step-by-Step Instructions:

1. **Push your code to GitHub**:
   Ensure all changes are committed and pushed:
   ```bash
   git add .
   git commit -m "Configure cloud deployment with dynamic PORT, DATABASE_URL, and migration script"
   git push origin main
   ```

2. **Log in to Render**:
   - Go to [dashboard.render.com](https://dashboard.render.com/) and sign in with GitHub.

3. **Deploy with Blueprint**:
   - Click the **"New +"** button at the top right.
   - Select **"Blueprint"**.
   - Connect your repository: `sanka-yashodhana/Flavour_pos_system`.
   - Render will detect [`render.yaml`](file:///c:/Food%20Application/flavour-pos/render.yaml) and automatically configure:
     - **Database**: PostgreSQL (`flavour-pos-db`)
     - **Web Service**: Docker (`flavour-pos-app`)
     - **Environment Variables**: Automatic `DATABASE_URL` binding and `AUTO_MIGRATE=true`.
   - Click **"Apply"**.

4. **Access Your Application**:
   Once the build completes:
   - **Storefront**: `https://<your-render-subdomain>.onrender.com`
   - **React POS**: `https://<your-render-subdomain>.onrender.com/pos`
   - **Admin Dashboard**: `https://<your-render-subdomain>.onrender.com/admin/dashboard.php`
   - **Status Check**: `https://<your-render-subdomain>.onrender.com/status.php`

---

## Option 2: Deploy on Railway

Railway deploys Docker containers and PostgreSQL seamlessly.

### Step-by-Step Instructions:

1. **Log in to Railway**:
   - Go to [railway.app](https://railway.app/) and sign in with GitHub.

2. **Create New Project**:
   - Click **"New Project"** -> **"Deploy from GitHub repo"**.
   - Select `sanka-yashodhana/Flavour_pos_system`.

3. **Add PostgreSQL Database**:
   - In your Railway project canvas, click **"+ New"** -> **"Database"** -> **"Add PostgreSQL"**.

4. **Connect Web Service to Database**:
   - Click on your web service card.
   - Go to the **"Variables"** tab.
   - Click **"Add Variable"** / **"Reference"** and set:
     - `DATABASE_URL` -> `${{Postgres.DATABASE_URL}}`
     - `AUTO_MIGRATE` -> `true`

5. **Generate Public Domain**:
   - In the web service settings, go to the **"Networking"** section.
   - Click **"Generate Domain"** to get a public URL (e.g., `https://flavour-pos-production.up.railway.app`).

---

## Default Login Credentials

After migration completes, the following accounts are initialized:
- **Admin**: Username: `admin` | Password: `admin123`
- **Cashier**: Username: `cashier` | Password: `cashier123`
- **Kitchen**: Username: `kitchen` | Password: `kitchen123`

*(Change these passwords upon first login).*
