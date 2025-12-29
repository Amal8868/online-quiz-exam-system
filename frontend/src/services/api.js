import axios from 'axios';

// Adjust this to match your XAMPP setup
const API_URL = 'http://localhost/online-quiz-exam-system/backend/api';

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token') || sessionStorage.getItem('token');
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

export const authAPI = {
  login: (credentials) => api.post('/teachers/login', credentials),
  register: (data) => api.post('/teachers/register', data),
  resetPassword: (data) => api.post('/teachers/reset-password', data),
};

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
  getQuizResults: (quizId) => api.get(`/quizzes/${quizId}/results`), // Added missing endpoint
  // Class Management
  getClasses: () => api.get('/classes'),
  createClass: (data) => api.post('/classes', data),
  getClassDetails: (id) => api.get(`/classes/${id}`),
  uploadClassStudents: (classId, students) => api.post(`/classes/${classId}/students`, { students }),
  globalImportStudents: (students) => api.post('/classes/import', { students }),
  setQuizClasses: (quizId, classIds) => api.post(`/quizzes/${quizId}/classes`, { class_ids: classIds }),
  getQuizzesByClass: (classId) => api.get(`/classes/${classId}/quizzes`),
  // Student Management
  checkStudentsExist: () => api.get('/students/check'),
  getClassStudents: (classId) => api.get(`/classes/${classId}/students`),

  // Results & Grading
  getClassQuizResults: (classId, quizId) => api.get(`/teachers/results/${classId}/${quizId}`),
  getStudentResult: (resultId) => api.get(`/teachers/results/${resultId}`),
  gradeAnswer: (resultId, data) => api.post(`/teachers/results/${resultId}/grade`, data),
  exportClassGradebook: (classId) => api.get(`/classes/${classId}/export`, { responseType: 'blob' }), // Change to blob request for auth
  controlStudent: (resultId, action) => api.post(`/results/${resultId}/control`, { action }),
};

export const studentAPI = {
  enterExam: (data) => api.post('/student/enter', data), // { room_code, student_id }
  getQuizStatus: (quizId) => api.get(`/student/quiz/${quizId}/status`),
  startExam: (data) => api.post('/student/start', data), // { quiz_id, student_db_id }
  getExamQuestions: (quizId) => api.get(`/student/exam/${quizId}/questions`),
  submitAnswer: (data) => api.post('/student/answer', data),
  finishExam: (data) => api.post('/student/finish', data),
  getResult: (resultId) => api.get(`/student/results/${resultId}`), // Added for ResultPage
  updateResultStatus: (resultId, status) => api.post(`/student/results/${resultId}/status`, { status }),
  getAttemptStatus: (resultId) => api.get(`/student/results/${resultId}/status_check`),
};

export default api;
