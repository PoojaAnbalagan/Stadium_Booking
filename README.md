# InBook - The Arena 🏟️

InBook is a premium, high-performance sports court booking application designed for modern sports enthusiasts. It provides a seamless interface for players to discover, book, and manage their favorite sports facilities with real-time availability and instant confirmation.

## ✨ Key Features

- **Premium UI/UX**: A state-of-the-art interface featuring glassmorphism, 3D animated backgrounds, and smooth scroll reveals.
- **Smart Booking System**: An intuitive booking bar that lets users quickly select dates, sports, and specific courts.
- **Dynamic Recommendations**: Personalized and trending recommendations based on user activity and popular sports.
- **Interactive Bento Grid**: A beautiful, modern grid layout for exploring different sports like Football, Cricket, Basketball, and Tennis.
- **Real-time Availability**: Instant court availability checking with a clean calendar-based time slot selection.
- **AI Chatbot**: Integrated support bot to help users with their booking queries in real-time.
- **Secure Authentication**: Robust user management with traditional login/signup and Google Social Auth integration.
- **Admin Command Center**: A powerful admin dashboard to manage users, courts, bookings, and site statistics.
- **User Profiles**: Revamped player profiles to track booking history and manage personal details.

## 🛠️ Technology Stack

- **Backend**: PHP 8.x
- **Database**: MySQL (MariaDB)
- **Frontend**: HTML5, Vanilla CSS3 (Glassmorphism), Vanilla JavaScript (ES6+)
- **Icons**: FontAwesome 6.4
- **Auth**: Google OAuth API
- **AI**: Integrated Chat API

## 🚀 Getting Started

### Prerequisites

- PHP server (e.g., WAMP, XAMPP, or MAMP)
- MySQL database
- Composer (for any future dependencies)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/PoojaAnbalagan/Stadium_Booking.git
   ```

2. **Database Setup**:
   - Create a database named `stadium_booking`.
   - Run the setup script by visiting `http://localhost/Stadium_Booking/setup_database.php` in your browser.
   - For admin setup, visit `http://localhost/Stadium_Booking/admin/setup_admin_database.php`.

3. **Configuration**:
   - Rename `config.php.example` to `config.php` (if applicable) or edit `config.php`.
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'stadium_booking');
     ```
   - (Optional) Add your Google Console Credentials and AI API Keys in `config.php`.

4. **Run**:
   - Place the project in your `www` or `htdocs` folder.
   - Access the homepage at `http://localhost/Stadium_Booking/index.php`.

## 📂 Project Structure

- `/admin`: Administrative dashboard and management scripts.
- `/src`: Static assets and specific component styles.
- `/lib`: Helper libraries and core functions.
- `index.php`: The main landing page and entry point.
- `config.php`: Central configuration for database and APIs.
- `style.css`: The primary design system and global styles.
- `calendar.php`: Time-slot selection and availability logic.
- `process_booking.php`: Core booking engine logic.

## 🔒 Security

- Sanitized user input using `mysqli_real_escape_string`.
- Secure session management for users and administrators.
- CSRF protection and role-based access control.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---
*Built with ❤️ for the sports community.*
