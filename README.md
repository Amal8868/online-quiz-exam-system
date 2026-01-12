# QuizMaster: Online Quiz & Exam System

QuizMaster is a comprehensive, modern web application designed for educational institutions to manage quizzes and exams efficiently. It provides a seamless experience for administrators, teachers, and students, featuring a robust backend and a premium, responsive frontend.

## 🚀 Key Features

### 👨‍🏫 For Teachers
- **Advanced Quiz Creation**: Build quizzes with multiple question types:
  - Multiple Choice (MCQ)
  - Multiple Selection (MSQ)
  - True/False
  - Short Answer (Manual Grading)
- **Multi-Class Assignment**: Assign a single quiz to multiple classes simultaneously using a modern, searchable dropdown.
- **Live Monitoring Board**: Track student progress in real-time as they take the exam.
- **Automated & Manual Grading**: Get instant results for objective questions and a dedicated board for grading short answers.
- **Timer & Room Codes**: Control exam access with unique 6-digit room codes and configurable timers.

### 🛡️ For Administrators
- **User Management**: Complete CRUD for Teachers and Students with status controls (Active/Deactivated).
- **Academic Organization**: Manage Classes and Subjects.
- **System Reports**: View analytics and performance reports across the entire system.


### 🎓 For Students
- **Interactive Exam Interface**: A clean, distraction-free environment for taking exams.
- **Instant Feedback**: View scores and correct answers immediately after submission (if enabled).


## 🛠️ Tech Stack

- **Frontend**: React.js (Vite), Tailwind CSS, Framer Motion (Animations), Heroicons.
- **Backend**: PHP (7.4+), RESTful API Architecture.
- **Database**: MySQL.
- **Server**: Compatible with XAMPP, or any Apache/Nginx environment.

## ⚙️ Installation & Setup

### Prerequisites
- XAMPP or any local PHP server.
- Node.js installed for frontend development.

### Backend Setup
1. Clone the repository to your `htdocs` folder.
2. Import the database schema:
   - Open phpMyAdmin.
   - Create a database named `online_quiz_exam_system`.
   - Import the `backend/database/database.sql` file.
3. Configure the database connection in `backend/api/config/database.php` if necessary.

### Frontend Setup
1. Navigate to the `frontend` directory:
   ```bash
   cd frontend
   ```
2. Install dependencies:
   ```bash
   npm install
   ```
3. Start the development server:
   ```bash
   npm start
   ```

## 🎨 UI/UX Features
- **Dark Mode Support**: Fully responsive design that respects system preferences.
- **Premium Aesthetics**: Glassmorphism effects, smooth transitions, and vibrant color palettes.
- **Mobile Friendly**: Optimized for tablets and smartphones.

## 👥 Development Team

1.  **Amal Abdulkadir Osman** (ID: C1220666)
2.  **Fardowsa Mohamed Ali** (ID: C1220667)
3.  **Yasir Ali Mohamud** (ID: C1220971)
4.  **Sihaam Ali Ahmed** (ID: C1220661)
   Class CA224

