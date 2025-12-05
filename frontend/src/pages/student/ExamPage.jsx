import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
    ClockIcon,
    ExclamationTriangleIcon,
    ChevronLeftIcon,
    ChevronRightIcon
} from '@heroicons/react/24/outline';
import { studentAPI } from '../../services/api';

const ExamPage = () => {
    const { roomCode } = useParams();
    const navigate = useNavigate();

    const [quiz, setQuiz] = useState(null);
    const [student, setStudent] = useState(null);
    const [questions, setQuestions] = useState([]);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
    const [answers, setAnswers] = useState({}); // { questionId: { selectedOptionId, answerText } }
    const [timeLeft, setTimeLeft] = useState(0);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [violations, setViolations] = useState(0); // eslint-disable-line no-unused-vars
    const [showWarning, setShowWarning] = useState(false);
    const [warningMessage, setWarningMessage] = useState('');

    // Load session data
    useEffect(() => {
        const sessionData = localStorage.getItem('student_session');
        if (!sessionData) {
            navigate('/join');
            return;
        }
        const { student, quiz } = JSON.parse(sessionData);
        setStudent(student);
        setQuiz(quiz);

        fetchExamData(roomCode);
    }, [navigate, roomCode]);

    const fetchExamData = async (code) => {
        try {
            const response = await studentAPI.getQuizByRoomCode(code);
            const quizData = response.data.data;
            setQuiz(quizData);
            setQuestions(quizData.questions || []);
            setTimeLeft(quizData.time_limit * 60); // Convert minutes to seconds
            setLoading(false);
        } catch (error) {
            console.error('Error fetching exam:', error);
            // navigate('/join');
        }
    };

    const handleSubmit = useCallback(async () => {
        setSubmitting(true);
        try {
            const formattedAnswers = Object.values(answers);
            const response = await studentAPI.submitExam({
                student_id: student.id,
                quiz_id: quiz.id,
                answers: formattedAnswers
            });

            navigate(`/results/${response.data.data.result_id}`);
        } catch (error) {
            console.error('Error submitting exam:', error);
            alert('Failed to submit exam. Please try again.');
            setSubmitting(false);
        }
    }, [student, quiz, answers, navigate]);

    // Timer
    useEffect(() => {
        if (loading || timeLeft <= 0) return;

        const timer = setInterval(() => {
            setTimeLeft((prev) => {
                if (prev <= 1) {
                    clearInterval(timer);
                    handleSubmit();
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [loading, timeLeft, handleSubmit]);

    // Anti-Cheat: Visibility Change & Blur
    const handleViolation = useCallback(async (type) => {
        if (submitting) return;

        try {
            const response = await studentAPI.logViolation({
                student_id: student.id,
                quiz_id: quiz.id,
                violation_type: type
            });

            const result = response.data.data;
            setViolations(result.violation_count);
            setWarningMessage(result.message);
            setShowWarning(true);

            if (result.action === 'kicked') {
                alert('You have been kicked from the exam due to multiple violations.');
                navigate('/');
            }
        } catch (error) {
            console.error('Error logging violation:', error);
        }
    }, [student, quiz, submitting, navigate]);

    useEffect(() => {
        if (loading) return;

        const onVisibilityChange = () => {
            if (document.hidden) {
                handleViolation('tab_switch');
            }
        };

        const onBlur = () => {
            handleViolation('minimize');
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('blur', onBlur);

        return () => {
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('blur', onBlur);
        };
    }, [loading, handleViolation]);

    const handleAnswer = (questionId, value, type) => {
        setAnswers(prev => ({
            ...prev,
            [questionId]: type === 'short_answer'
                ? { answer_text: value, question_id: questionId }
                : { selected_option_id: value, question_id: questionId }
        }));
    };

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    };

    if (loading) return <div className="flex justify-center items-center h-screen">Loading Exam...</div>;

    const currentQuestion = questions[currentQuestionIndex];

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12">
            {/* Header */}
            <div className="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900 dark:text-white">{quiz.title}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Question {currentQuestionIndex + 1} of {questions.length}</p>
                    </div>
                    <div className={`flex items-center space-x-2 text-xl font-mono font-bold ${timeLeft < 60 ? 'text-red-600 animate-pulse' : 'text-primary-600'}`}>
                        <ClockIcon className="h-6 w-6" />
                        <span>{formatTime(timeLeft)}</span>
                    </div>
                </div>
            </div>

            {/* Warning Modal */}
            <AnimatePresence>
                {showWarning && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm"
                    >
                        <motion.div
                            initial={{ scale: 0.9 }}
                            animate={{ scale: 1 }}
                            className="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full shadow-2xl border-2 border-red-500"
                        >
                            <div className="flex items-center space-x-3 text-red-600 mb-4">
                                <ExclamationTriangleIcon className="h-8 w-8" />
                                <h3 className="text-lg font-bold">Anti-Cheat Warning</h3>
                            </div>
                            <p className="text-gray-700 dark:text-gray-300 mb-6">{warningMessage}</p>
                            <button
                                onClick={() => setShowWarning(false)}
                                className="btn btn-primary w-full bg-red-600 hover:bg-red-700"
                            >
                                I Understand
                            </button>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>

            {/* Question Area */}
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <motion.div
                    key={currentQuestion.id}
                    initial={{ opacity: 0, x: 20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.3 }}
                    className="card min-h-[400px] flex flex-col"
                >
                    <div className="flex-1">
                        <h2 className="text-xl font-medium text-gray-900 dark:text-white mb-6">
                            {currentQuestion.question_text}
                        </h2>

                        <div className="space-y-4">
                            {/* MCQ & True/False Options */}
                            {(currentQuestion.question_type === 'mcq' || currentQuestion.question_type === 'true_false') && (
                                <div className="space-y-3">
                                    {currentQuestion.options.map((option) => (
                                        <label
                                            key={option.id}
                                            className={`flex items-center p-4 rounded-lg border-2 cursor-pointer transition-all ${answers[currentQuestion.id]?.selected_option_id === option.id
                                                ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20'
                                                : 'border-gray-200 dark:border-gray-700 hover:border-primary-300'
                                                }`}
                                        >
                                            <input
                                                type="radio"
                                                name={`question_${currentQuestion.id}`}
                                                className="h-5 w-5 text-primary-600 focus:ring-primary-500 border-gray-300"
                                                checked={answers[currentQuestion.id]?.selected_option_id === option.id}
                                                onChange={() => handleAnswer(currentQuestion.id, option.id, currentQuestion.question_type)}
                                            />
                                            <span className="ml-3 text-gray-900 dark:text-white font-medium">
                                                {option.option_text}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}

                            {/* Short Answer */}
                            {currentQuestion.question_type === 'short_answer' && (
                                <textarea
                                    rows={6}
                                    className="input text-lg"
                                    placeholder="Type your answer here..."
                                    value={answers[currentQuestion.id]?.answer_text || ''}
                                    onChange={(e) => handleAnswer(currentQuestion.id, e.target.value, 'short_answer')}
                                />
                            )}
                        </div>
                    </div>

                    {/* Navigation */}
                    <div className="mt-8 flex justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                        <button
                            onClick={() => setCurrentQuestionIndex(prev => Math.max(0, prev - 1))}
                            disabled={currentQuestionIndex === 0}
                            className="btn btn-outline disabled:opacity-50"
                        >
                            <ChevronLeftIcon className="h-5 w-5" /> Previous
                        </button>

                        {currentQuestionIndex === questions.length - 1 ? (
                            <button
                                onClick={handleSubmit}
                                disabled={submitting}
                                className="btn btn-primary bg-green-600 hover:bg-green-700"
                            >
                                {submitting ? 'Submitting...' : 'Submit Exam'}
                            </button>
                        ) : (
                            <button
                                onClick={() => setCurrentQuestionIndex(prev => Math.min(questions.length - 1, prev + 1))}
                                className="btn btn-primary"
                            >
                                Next <ChevronRightIcon className="h-5 w-5" />
                            </button>
                        )}
                    </div>
                </motion.div>

                {/* Question Palette */}
                <div className="mt-8 grid grid-cols-5 sm:grid-cols-10 gap-2">
                    {questions.map((q, idx) => (
                        <button
                            key={q.id}
                            onClick={() => setCurrentQuestionIndex(idx)}
                            className={`h-10 w-10 rounded-lg flex items-center justify-center text-sm font-medium transition-colors ${currentQuestionIndex === idx
                                ? 'bg-primary-600 text-white'
                                : answers[q.id]
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                }`}
                        >
                            {idx + 1}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default ExamPage;
