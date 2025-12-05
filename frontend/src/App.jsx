import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { ThemeProvider } from './contexts/ThemeContext';
import Layout from './components/Layout';
import LandingPage from './pages/LandingPage';
import TeacherLogin from './pages/auth/TeacherLogin';
import TeacherRegister from './pages/auth/TeacherRegister';
import TeacherDashboard from './pages/teacher/Dashboard';
import CreateQuiz from './pages/teacher/CreateQuiz';
import StudentJoin from './pages/student/Join';
import ExamPage from './pages/student/ExamPage';
import ResultPage from './pages/student/ResultPage';
import NotFound from './pages/NotFound';

function App() {
  return (
    <ThemeProvider>
      <Router>
        <Routes>
          {/* Public Routes */}
          <Route path="/" element={<LandingPage />} />
          
          {/* Auth Routes */}
          <Route path="/teacher/login" element={<TeacherLogin />} />
          <Route path="/teacher/register" element={<TeacherRegister />} />
          
          {/* Teacher Routes */}
          <Route path="/teacher" element={<Layout type="teacher" />}>
            <Route index element={<TeacherDashboard />} />
            <Route path="create-quiz" element={<CreateQuiz />} />
            {/* Add more teacher routes here */}
          </Route>
          
          {/* Student Routes */}
          <Route path="/join" element={<StudentJoin />} />
          <Route path="/exam/:roomCode" element={<ExamPage />} />
          <Route path="/results/:resultId" element={<ResultPage />} />
          
          {/* 404 Route */}
          <Route path="*" element={<NotFound />} />
        </Routes>
      </Router>
    </ThemeProvider>
  );
}

export default App;
