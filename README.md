# 🏋️ gymportal.io

A modern gym management system built with Laravel and Vue.js, featuring member management, course bookings, payments, and comprehensive admin tools.

## 🚀 Tech Stack

-   **Backend**: Laravel 12 (PHP 8.2+)
-   **Frontend**: Vue.js 3 with Inertia.js
-   **Styling**: Tailwind CSS
-   **Database**: MySQL (via DDEV)
-   **Payment Processing**: Mollie API
-   **Development Environment**: DDEV
-   **Frontend Build Tool**: Vite

## 📋 Prerequisites

Before you begin, ensure you have the following tools installed on your system:

### Required Dependencies

-   **Node.js & NPM**: [Download from nodejs.org](https://nodejs.org/)

    ```bash
    # Verify installation
    node --version
    npm --version
    ```

-   **DDEV**: [Installation Guide](https://ddev.readthedocs.io/en/stable/users/install/)

    ```bash
    # Verify installation
    ddev version
    ```

-   **mkcert**: [Installation Guide](https://github.com/FiloSottile/mkcert)

    ```bash
    # Verify installation
    mkcert --version
    ```

## 🛠️ Installation

Follow these steps to set up your development environment:

### 1. Clone the Repository

```bash
git clone <repository-url>
cd gymportal-io
```

### 2. Install Dependencies

#### Backend Dependencies

```bash
ddev composer install
```

#### Frontend Dependencies

```bash
npm install
```

### 3. Configure HTTPS for Local Development

```bash
mkcert -install
```

This command installs the local CA certificate, enabling HTTPS on your local development environment.

### 4. Start DDEV Environment

```bash
ddev start
```

## 🏃‍♂️ Running the Application

### Frontend Development Server

Start the Vite development server for hot module replacement:

```bash
npm run dev
```

### Backend Setup

#### First Time Setup

If this is your first time running the application:

1. **Generate Application Key**

    ```bash
    ddev php artisan key:generate
    ```

2. **Seed the Database**

    ```bash
    ddev php artisan db:seed
    ```

#### Subsequent Runs

For regular development sessions:

```bash
ddev launch
```

This command will open your application in the default browser.

## 🌐 Accessing the Application

### Main Application

Navigate to: **[https://gymportal-io.ddev.site]()**

### First-Time Login Setup

1. **Go to the application URL**: [https://gymportal-io.ddev.site]()
2. **Click on "Passwort vergessen?" (Forgot Password)**
3. **Enter the test email**: `max@fitzone.de`
4. **Check the local mailbox**:

    ```bash
    ddev launch -m
    ```

    This opens Mailpit, the local mail testing interface
5. **Click on the password reset link** in the received email
6. **Set a new password** of your choice
7. **Login** with the email and your new password

🎉 **You're all set!** You can now access the gymportal.io application.

## 🗄️ Database Management

### Access Database

```bash
# MySQL CLI
ddev mysql

# Or use a GUI tool with these credentials:
# Host: 127.0.0.1
# Port: (check with `ddev describe`)
# Username: root
# Password: root
# Database: db
```

### Common Database Commands

```bash
# Run migrations
ddev php artisan migrate

# Rollback migrations
ddev php artisan migrate:rollback

# Seed database
ddev php artisan db:seed

# Fresh migration with seeding
ddev php artisan migrate:fresh --seed
```

## 🛠️ Development Tools

### Artisan Commands

```bash
# List all available commands
ddev php artisan list

# Generate models, controllers, etc.
ddev php artisan make:model ModelName
ddev php artisan make:controller ControllerName
ddev php artisan make:migration create_table_name
```

## 📝 Project Structure

```
gymportal-io/
├── app/
│   ├── Http/Controllers/    # API & Web controllers
│   ├── Models/             # Eloquent models
│   ├── Policies/           # Authorization policies
│   └── Services/           # Business logic services
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   ├── js/                # Vue.js components
│   ├── css/               # Stylesheets
│   └── views/             # Blade templates
├── routes/
│   ├── web.php            # Web routes
│   └── api.php            # API routes
└── public/                # Public assets
```

## 🐛 Troubleshooting

### Common Issues

**DDEV not starting?**

```bash
ddev restart
```

**Database connection issues?**

```bash
ddev restart
ddev php artisan migrate
```

**Frontend not updating?**

```bash
npm run dev
# Make sure Vite dev server is running
```

**HTTPS certificate issues?**

```bash
mkcert -install
ddev restart
```

### Getting Help

-   Check DDEV logs: `ddev logs`
-   Laravel logs: `storage/logs/laravel.log`
-   Browser developer tools for frontend issues

## 📚 Additional Resources

-   [Laravel Documentation](https://laravel.com/docs)
-   [Vue.js Documentation](https://vuejs.org/guide/)
-   [Inertia.js Documentation](https://inertiajs.com/)
-   [DDEV Documentation](https://ddev.readthedocs.io/)
-   [Tailwind CSS Documentation](https://tailwindcss.com/docs)

---

**Happy coding! 🚀**

> Need help? Check the troubleshooting section above or reach out to the development team.
