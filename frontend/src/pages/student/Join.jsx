import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { studentAPI } from '../../services/api';

const Join = () => {
    const navigate = useNavigate();
    const [step, setStep] = useState(1);
    const [formData, setFormData] = useState({
        roomCode: '',
        studentId: '',
        classId: '', // Will be fetched from backend based on room code? No, student needs to know class? 
        // Actually, room code -> quiz -> class. 
        // But student verification needs class_id.
        // Let's assume student enters Room Code + Student ID.
        // Backend should verify if student belongs to the class of that quiz.
    });
    const [quizDetails, setQuizDetails] = useState(null);
    const [studentDetails, setStudentDetails] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleRoomCodeSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            // 1. Get Quiz Details from Room Code
            const response = await studentAPI.getQuizByRoomCode(formData.roomCode);
            setQuizDetails(response.data.data);
            setFormData(prev => ({ ...prev, classId: response.data.data.class_id }));
            setStep(2);
        } catch (err) {
            setError(err.response?.data?.error || 'Invalid Room Code');
        } finally {
            setLoading(false);
        }
    };

    const handleStudentVerify = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            // 2. Verify Student ID against the Class ID from the quiz
            const response = await studentAPI.verify({
                student_id: formData.studentId,
                class_id: quizDetails.class_id
            });
            setStudentDetails(response.data.data.student);
            setStep(3);
        } catch (err) {
            setError(err.response?.data?.error || 'Student not found in this class');
        } finally {
            setLoading(false);
        }
    };

    const startExam = () => {
        // Store student info in session/local storage for the exam session
        localStorage.setItem('student_session', JSON.stringify({
            student: studentDetails,
            quiz: quizDetails
        }));
        navigate(`/exam/${formData.roomCode}`);
    };

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                <motion.h2
                    className="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white"
                    initial={{ opacity: 0, y: -20 }}
                    animate={{ opacity: 1, y: 0 }}
                >
                    Join Quiz
                </motion.h2>
            </div>

            <motion.div
                className="mt-8 sm:mx-auto sm:w-full sm:max-w-md"
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 }}
            >
                <div className="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-gray-100 dark:border-gray-700">

                    {error && (
                        <div className="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-md text-sm">
                            {error}
                        </div>
                    )}

                    {step === 1 && (
                        <form onSubmit={handleRoomCodeSubmit} className="space-y-6">
                            <div>
                                <label htmlFor="roomCode" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Room Code
                                </label>
                                <div className="mt-1">
                                    <input
                                        id="roomCode"
                                        type="text"
                                        required
                                        className="input text-center text-2xl tracking-widest uppercase"
                                        placeholder="ABC123"
                                        maxLength={6}
                                        value={formData.roomCode}
                                        onChange={(e) => setFormData({ ...formData, roomCode: e.target.value.toUpperCase() })}
                                    />
                                </div>
                            </div>
                            <button
                                type="submit"
                                disabled={loading}
                                className="btn btn-primary w-full"
                            >
                                {loading ? 'Verifying...' : 'Next'}
                            </button>
                        </form>
                    )}

                    {step === 2 && (
                        <form onSubmit={handleStudentVerify} className="space-y-6">
                            <div className="text-center mb-6">
                                <h3 className="text-lg font-medium text-gray-900 dark:text-white">{quizDetails.title}</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400">{quizDetails.class_name}</p>
                            </div>

                            <div>
                                <label htmlFor="studentId" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Student ID
                                </label>
                                <div className="mt-1">
                                    <input
                                        id="studentId"
                                        type="text"
                                        required
                                        className="input"
                                        placeholder="Enter your Student ID"
                                        value={formData.studentId}
                                        onChange={(e) => setFormData({ ...formData, studentId: e.target.value })}
                                    />
                                </div>
                            </div>
                            <button
                                type="submit"
                                disabled={loading}
                                className="btn btn-primary w-full"
                            >
                                {loading ? 'Verifying...' : 'Verify Identity'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setStep(1)}
                                className="btn btn-outline w-full mt-2"
                            >
                                Back
                            </button>
                        </form>
                    )}

                    {step === 3 && (
                        <div className="space-y-6 text-center">
                            <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-full w-20 h-20 mx-auto flex items-center justify-center">
                                <span className="text-3xl">👋</span>
                            </div>

                            <div>
                                <h3 className="text-xl font-bold text-gray-900 dark:text-white">Is this you?</h3>
                                <p className="mt-2 text-lg text-primary-600 dark:text-primary-400 font-medium">{studentDetails.name}</p>
                                <p className="text-sm text-gray-500 dark:text-gray-400">{studentDetails.student_id}</p>
                            </div>

                            <div className="pt-4 space-y-3">
                                <button
                                    onClick={startExam}
                                    className="btn btn-primary w-full text-lg py-3"
                                >
                                    Yes, Start Exam
                                </button>
                                <button
                                    onClick={() => setStep(2)}
                                    className="btn btn-outline w-full"
                                >
                                    No, that's not me
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </motion.div>
        </div>
    );
};

export default Join;
