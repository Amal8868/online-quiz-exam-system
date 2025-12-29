import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { ThemeProvider } from './contexts/ThemeContext';
import Layout from './components/Layout';
import LandingPage from './pages/LandingPage';
import TeacherLogin from './pages/auth/TeacherLogin';
import TeacherRegister from './pages/auth/TeacherRegister';
import ForgotPassword from './pages/auth/ForgotPassword';
import ResetPassword from './pages/auth/ResetPassword';
import TeacherDashboard from './pages/teacher/Dashboard';
import CreateQuiz from './pages/teacher/CreateQuiz';
import QuizManage from './pages/teacher/QuizManage';
import QuizResults from './pages/teacher/QuizResults'; // New Import
import StudentJoin from './pages/student/Join';
import WaitingRoom from './pages/student/WaitingRoom';
import ExamPage from './pages/student/ExamPage';
import ResultPage from './pages/student/ResultPage';
import ClassList from './pages/teacher/ClassList';
import ClassDetails from './pages/teacher/ClassDetails';
import GlobalImport from './pages/teacher/GlobalImport';

import ResultsClasses from './pages/teacher/ResultsClasses';
import ResultsClassQuizzes from './pages/teacher/ResultsClassQuizzes';
import QuizGradingBoard from './pages/teacher/QuizGradingBoard';
import StudentGradingView from './pages/teacher/StudentGradingView';
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
          <Route path="/teacher/forgot-password" element={<ForgotPassword />} />
          <Route path="/teacher/reset-password" element={<ResetPassword />} />

          {/* Teacher Routes */}
          <Route path="/teacher" element={<Layout type="teacher" />}>
            <Route index element={<TeacherDashboard />} />
            <Route path="create-quiz" element={<CreateQuiz />} />
            <Route path="quiz/:quizId" element={<QuizManage />} />
            <Route path="quiz/:quizId/questions" element={<QuizManage />} />
            <Route path="quiz/:quizId/results" element={<QuizResults />} /> {/* New Route */}
            <Route path="classes" element={<ClassList />} />
            <Route path="classes/:id" element={<ClassDetails />} />
            <Route path="classes/import" element={<GlobalImport />} />


            {/* Results & Grading Routes */}
            <Route path="results" element={<ResultsClasses />} />
            <Route path="results/class/:classId" element={<ResultsClassQuizzes />} />
            <Route path="results/class/:classId/quiz/:quizId" element={<QuizGradingBoard />} />
            <Route path="results/grading/:resultId" element={<StudentGradingView />} />
          </Route>

          {/* Student Routes */}
          <Route path="/join" element={<StudentJoin />} />
          <Route path="/student/waiting/:quizId" element={<WaitingRoom />} />
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
