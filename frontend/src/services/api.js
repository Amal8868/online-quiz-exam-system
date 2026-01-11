import axios from 'axios';

/**
 * THE DIGITAL BRIDGE (api.js)
 * 
 * This file is like the phone line between our React frontend and our PHP backend.
 * Every time we want to save a quiz, login a user, or fetch results, we "call" the
 * server using the functions in this file.
 */

// Adjust this URL to match your XAMPP setup!
const API_URL = 'http://localhost/online-quiz-exam-system/backend/api/index.php';

// We use AXIOS because it's like a smart messenger service. 
// It handles headers, JSON, and errors way better than the browser's default 'fetch'.
const api = axios.create({
  baseURL: API_URL,
  withCredentials: true, // IMPORTANT: This tells the browser to include our Session ID (Cookies) in every request.
});

/**
 * THE SECURITY CHECKPOINT (Request Interceptor)
 * 
 * Before any message leaves our app, we stop it here to attach our "Security Badge" (JWT Token).
 * If we didn't do this, the server wouldn't know who we are!
 */
api.interceptors.request.use(
  (config) => {
    // We grab the token from the "Safe" (LocalStorage or SessionStorage).
    const token = localStorage.getItem('token') || sessionStorage.getItem('token');
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`; // Hand the badge to the server.
    }
    return config;
  },
  (error) => Promise.reject(error)
);

/**
 * THE EMERGENCY EXIT (Response Interceptor)
 * 
 * When the server sends a message back, we check if it's "401 Unauthorized".
 * This usually means our session expired. Instead of letting the app break, 
 * we automatically "Kick" the user out to the Login page for safety.
 */
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      // Clear out the stale data.
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      sessionStorage.removeItem('token');
      sessionStorage.removeItem('user');

      // Send them home if they aren't already there.
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

/**
 * ORGANIZING OUR REQUESTS
 * 
 * To keep things tidy, we grouped our "phone numbers" into sections.
 * It's like having a directory for the Principal (Admin), Staff (Teacher), and Students.
 */

// --- AUTH: Logging in and out ---
export const authAPI = {
  login: (credentials) => api.post('/teachers/login', credentials),
  register: (data) => api.post('/teachers/register', data),
  resetPassword: (data) => api.post('/teachers/reset-password', data),
  updateProfilePicture: (formData) => api.post('/auth/update-profile', formData, {
    headers: { 'Content-Type': 'multipart/form-data' } // We tell the server: "Heads up, a file is coming!"
  }),
  logout: () => api.post('/auth/logout'),
  getCurrentUser: () => api.get('/auth/me'),
};

// --- TEACHER: Managing Quizzes, Classes, and Grading ---
export const teacherAPI = {
  getDashboardStats: () => api.get('/teacher/dashboard'),
  getQuizzes: () => api.get('/quizzes'),
  getQuiz: (id) => api.get(`/quizzes/${id}`),
  createQuiz: (data) => api.post('/quizzes', data),
  addQuestion: (quizId, data) => api.post(`/quizzes/${quizId}/questions`, data),
  updateQuestion: (questionId, data) => api.put(`/questions/${questionId}`, data),
  deleteQuestion: (questionId) => api.delete(`/questions/${questionId}`),
  uploadRoster: (quizId, formData) => api.post(`/quizzes/${quizId}/roster`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  deleteQuiz: (quizId) => api.delete(`/quizzes/${quizId}`),
  updateQuizStatus: (quizId, status) => api.post(`/quizzes/${quizId}/status`, { status }),
  adjustTime: (quizId, adjustment) => api.post(`/quizzes/${quizId}/adjust-time`, { adjustment }),
  getLiveMonitoring: (quizId) => api.get(`/quizzes/${quizId}/monitoring`),
  getQuizResults: (quizId) => api.get(`/quizzes/${quizId}/results`),

  // Classes
  getClasses: () => api.get('/classes'),
  createClass: (data) => api.post('/classes', data),
  getClassDetails: (id) => api.get(`/classes/${id}`),
  uploadClassStudents: (classId, students) => api.post(`/classes/${classId}/students`, { students }),
  globalImportStudents: (students) => api.post('/classes/import', { students }),
  setQuizClasses: (quizId, classIds) => api.post(`/quizzes/${quizId}/classes`, { class_ids: classIds }),
  getQuizzesByClass: (classId) => api.get(`/classes/${classId}/quizzes`),

  // Students
  checkStudentsExist: () => api.get('/students/check'),
  getClassStudents: (classId) => api.get(`/classes/${classId}/students`),
  getClassSubjects: (classId) => api.get(`/classes/${classId}/subjects`),

  // Results & Grading
  getClassQuizResults: (classId, quizId) => api.get(`/teachers/results/${classId}/${quizId}`),
  getStudentResult: (resultId) => api.get(`/teachers/results/${resultId}`),
  gradeAnswer: (resultId, data) => api.post(`/teachers/results/${resultId}/grade`, data),
  exportClassGradebook: (classId) => api.get(`/classes/${classId}/export`, { responseType: 'blob' }),
  controlStudent: (resultId, action) => api.post(`/results/${resultId}/control`, { action }),
  changePassword: (data) => api.post('/teachers/change-password', data),
};

// --- STUDENT: Entering rooms and taking exams ---
export const studentAPI = {
  enterExam: (data) => api.post('/student/enter', data),
  getQuizStatus: (quizId) => api.get(`/student/quiz/${quizId}/status`),
  startExam: (data) => api.post('/student/start', data),
  getExamQuestions: (quizId) => api.get(`/student/exam/${quizId}/questions`),
  submitAnswer: (data) => api.post('/student/answer', data),
  finishExam: (data) => api.post('/student/finish', data),
  getResult: (resultId) => api.get(`/student/results/${resultId}`),
  updateResultStatus: (resultId, status) => api.post(`/student/results/${resultId}/status`, { status }),
  getAttemptStatus: (resultId) => api.get(`/student/results/${resultId}/status_check`),
};

// --- ADMIN: Master system settings and User Management ---
export const adminAPI = {
  createUser: (data) => api.post('/admin/users', data),
  getUsers: (type) => api.get('/admin/users', { params: { type } }),
  updateUser: (id, data) => api.post(`/admin/users/${id}`, data),
  deleteUser: (id) => api.delete(`/admin/users/${id}`),

  createClass: (data) => api.post('/admin/classes', data),
  assignTeacher: (classId, teacherId) => api.post(`/admin/classes/${classId}/assign-teacher`, { teacher_id: teacherId }),
  assignStudent: (classId, studentId) => api.post(`/admin/classes/${classId}/assign-student`, { student_id: studentId }),
  getAllClasses: () => api.get('/admin/classes'),
  getClassDetails: (id) => api.get(`/admin/classes/${id}`),
  updateClass: (id, data) => api.put(`/admin/classes/${id}`, data),
  deleteClass: (id) => api.delete(`/admin/classes/${id}`),
  getStats: () => api.get('/admin/stats'),
  getReports: () => api.get('/admin/reports'),

  // Subjects
  getSubjects: () => api.get('/admin/subjects'),
  getSubject: (id) => api.get(`/admin/subjects/${id}`),
  createSubject: (data) => api.post('/admin/subjects', data),
  updateSubject: (id, data) => api.put(`/admin/subjects/${id}`, data),
  deleteSubject: (id) => api.delete(`/admin/subjects/${id}`),
};

export default api;
