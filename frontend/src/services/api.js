import axios from 'axios';

const API_URL = 'http://localhost:8000/backend/api'; // Updated to port 8000 for PHP built-in server

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add a request interceptor to include the JWT token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add a response interceptor to handle auth errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      // Redirect to login if unauthorized (optional: clear token)
      // localStorage.removeItem('token');
      // window.location.href = '/teacher/login';
    }
    return Promise.reject(error);
  }
);

export const authAPI = {
  login: (credentials) => api.post('/teachers/login', credentials),
  register: (data) => api.post('/teachers/register', data),
};

export const teacherAPI = {
  getDashboardStats: () => api.get('/teacher/dashboard'),
  getClasses: () => api.get('/classes'),
  createClass: (data) => api.post('/classes', data),
  getQuizzes: () => api.get('/quizzes'),
  getQuiz: (id) => api.get(`/quizzes/${id}`),
  createQuiz: (data) => api.post('/quizzes', data),
  deleteQuiz: (id) => api.delete(`/quizzes/${id}`),
  addQuestion: (quizId, data) => api.post(`/quizzes/${quizId}/questions`, data),
  updateQuestion: (id, data) => api.put(`/questions/${id}`, data),
  deleteQuestion: (id) => api.delete(`/questions/${id}`),
  getQuizResults: (quizId) => api.get(`/quizzes/${quizId}/results`),
};

export const studentAPI = {
  verify: (data) => api.post('/student/verify', data),
  getQuizByRoomCode: (code) => api.get(`/student/quiz/${code}`),
  submitExam: (data) => api.post('/student/submit', data),
  logViolation: (data) => api.post('/student/violation', data),
  getResult: (id) => api.get(`/student/results/${id}`),
};

export default api;
