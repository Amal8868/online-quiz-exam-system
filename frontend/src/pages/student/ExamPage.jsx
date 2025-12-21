import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
    ClockIcon,
    ExclamationTriangleIcon,
    ChevronLeftIcon,
    ChevronRightIcon
} from '@heroicons/react/24/outline';
import { studentAPI } from '../../services/api';

const ExamPage = () => {
    const navigate = useNavigate();

    const [quiz, setQuiz] = useState(null);
    const [student, setStudent] = useState(null);
    const [resultId, setResultId] = useState(null);
    const [questions, setQuestions] = useState([]);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
    const [answers, setAnswers] = useState({}); // { questionId: { selectedOptionId, answerText } }
    const [timeLeft, setTimeLeft] = useState(0);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [showWarning, setShowWarning] = useState(false);
    const [warningMessage, setWarningMessage] = useState('');
    const [questionStartTime, setQuestionStartTime] = useState(Date.now());

    const handleSubmit = useCallback(async () => {
        if (submitting || !resultId) return;
        setSubmitting(true);
        try {
            const response = await studentAPI.finishExam({
                result_id: resultId
            });

            if (response.data.success) {
                navigate(`/results/${resultId}`);
            }
        } catch (error) {
            console.error('Error submitting exam:', error);
            alert('Failed to submit exam. Please try again.');
            setSubmitting(false);
        }
    }, [resultId, submitting, navigate]);

    const handleViolation = useCallback(async (type) => {
        if (submitting || !student || !quiz) return;

        try {
            await studentAPI.logViolation({
                student_id: student.id,
                quiz_id: quiz.id,
                violation_type: type
            });

            setWarningMessage("Please stay on the exam tab! Violations are recorded.");
            setShowWarning(true);

        } catch (error) {
            console.error('Error logging violation:', error);
        }
    }, [student, quiz, submitting]);

    const handleAnswer = async (questionId, value, type) => {
        const now = Date.now();
        const timeTaken = Math.round((now - questionStartTime) / 1000);

        const answerPayload = type === 'short_answer'
            ? { answer_text: value, question_id: questionId }
            : { selected_option_id: value, question_id: questionId };

        setAnswers(prev => ({
            ...prev,
            [questionId]: answerPayload
        }));

        if (resultId) {
            try {
                let answerStr = '';
                if (type === 'short_answer') {
                    answerStr = value;
                } else {
                    answerStr = value; // Option ID
                }

                await studentAPI.submitAnswer({
                    result_id: resultId,
                    question_id: questionId,
                    answer: answerStr,
                    time_taken: timeTaken
                });
            } catch (e) {
                console.error("Auto-save failed", e);
            }
        }
    };

    const formatTime = (seconds) => {
        if (!seconds) return "0:00";
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    };

    // Load session data
    useEffect(() => {
        const sessionData = localStorage.getItem('student_session');
        if (!sessionData) {
            navigate('/join');
            return;
        }
        const parsed = JSON.parse(sessionData);
        setStudent(parsed.student);
        setQuiz(parsed.quiz);
    }, [navigate]);

    // Fetch questions and start exam
    useEffect(() => {
        if (!quiz || !student) return;

        const initExam = async () => {
            try {
                // 1. Start Exam Session
                const startRes = await studentAPI.startExam({
                    quiz_id: quiz.id,
                    student_db_id: student.id
                });

                if (startRes.data.success) {
                    setResultId(startRes.data.data.result_id);
                }

                // 2. Fetch Questions
                const qRes = await studentAPI.getExamQuestions(quiz.id);
                if (qRes.data.success) {
                    setQuestions(qRes.data.data);

                    // 3. Synchronized Timer Calculation
                    const totalDurationSeconds = quiz.time_limit * 60;

                    if (quiz.start_time && quiz.server_time) {
                        const startTime = new Date(quiz.start_time.replace(/-/g, '/')).getTime();
                        const serverTimeAtCapture = new Date(quiz.server_time.replace(/-/g, '/')).getTime();
                        const elapsedSeconds = Math.floor((serverTimeAtCapture - startTime) / 1000);
                        const calculatedTimeLeft = totalDurationSeconds - elapsedSeconds;

                        if (calculatedTimeLeft <= 0) {
                            setTimeLeft(0);
                            setLoading(false);
                            handleSubmit();
                            return;
                        } else {
                            setTimeLeft(calculatedTimeLeft);
                        }
                    } else {
                        setTimeLeft(totalDurationSeconds);
                    }
                }

                setLoading(false);

                // 4. Update status to 'in_progress'
                try {
                    const resId = startRes.data.data.result_id;
                    if (resId) {
                        await studentAPI.updateResultStatus(resId, 'in_progress');
                    }
                } catch (e) {
                    console.error("Failed to update status to in_progress", e);
                }
            } catch (error) {
                console.error('Error initializing exam:', error);
                alert('Failed to load exam. ' + (error.response?.data?.message || ''));
            }
        };

        if (loading) {
            initExam();
        }
    }, [quiz, student, loading, handleSubmit]);

    // Timer Interval
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

    // Anti-Cheat Events
    useEffect(() => {
        if (loading) return;

        const onVisibilityChange = () => {
            if (document.hidden) {
                handleViolation('tab_switch');
            }
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        return () => {
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    }, [loading, handleViolation]);

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
                {currentQuestion && (
                    <motion.div
                        key={currentQuestion.id}
                        initial={{ opacity: 0, x: 20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.3 }}
                        className="card min-h-[400px] flex flex-col bg-white p-6 rounded-lg shadow"
                    >
                        <div className="flex-1">
                            <h2 className="text-xl font-medium text-gray-900 dark:text-white mb-6">
                                {currentQuestion.question_text}
                            </h2>

                            <div className="space-y-4">
                                {(currentQuestion.question_type === 'multiple_choice' || currentQuestion.question_type === 'true_false' || currentQuestion.question_type === 'mcq') && (
                                    <div className="space-y-3">
                                        {currentQuestion.options && currentQuestion.options.map((option) => (
                                            <label
                                                key={option.id || option.text}
                                                className={`flex items-center p-4 rounded-lg border-2 cursor-pointer transition-all ${answers[currentQuestion.id]?.selected_option_id === (option.id || option.text)
                                                    ? 'border-indigo-600 bg-indigo-50'
                                                    : 'border-gray-200 hover:border-indigo-300'
                                                    }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name={`question_${currentQuestion.id}`}
                                                    className="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                    checked={answers[currentQuestion.id]?.selected_option_id === (option.id || option.text)}
                                                    onChange={() => handleAnswer(currentQuestion.id, (option.id || option.text), currentQuestion.question_type)}
                                                />
                                                <span className="ml-3 text-gray-900 dark:text-white font-medium">
                                                    {option.option_text || option.text}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                )}

                                {currentQuestion.question_type === 'short_answer' && (
                                    <textarea
                                        rows={6}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-lg"
                                        placeholder="Type your answer here..."
                                        value={answers[currentQuestion.id]?.answer_text || ''}
                                        onChange={(e) => handleAnswer(currentQuestion.id, e.target.value, 'short_answer')}
                                    />
                                )}
                            </div>
                        </div>

                        <div className="mt-8 flex justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                            <button
                                onClick={() => {
                                    setCurrentQuestionIndex(prev => Math.max(0, prev - 1));
                                    setQuestionStartTime(Date.now());
                                }}
                                disabled={currentQuestionIndex === 0}
                                className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <ChevronLeftIcon className="h-5 w-5 inline mr-1" /> Previous
                            </button>

                            {currentQuestionIndex === questions.length - 1 ? (
                                <button
                                    onClick={handleSubmit}
                                    disabled={submitting}
                                    className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                >
                                    {submitting ? 'Submitting...' : 'Submit Exam'}
                                </button>
                            ) : (
                                <button
                                    onClick={() => {
                                        setCurrentQuestionIndex(prev => Math.min(questions.length - 1, prev + 1));
                                        setQuestionStartTime(Date.now());
                                    }}
                                    className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    Next <ChevronRightIcon className="h-5 w-5 inline ml-1" />
                                </button>
                            )}
                        </div>
                    </motion.div>
                )}

                <div className="mt-8 grid grid-cols-5 sm:grid-cols-10 gap-2">
                    {questions.map((q, idx) => (
                        <button
                            key={q.id}
                            onClick={() => {
                                setCurrentQuestionIndex(idx);
                                setQuestionStartTime(Date.now());
                            }}
                            className={`h-10 w-10 rounded-lg flex items-center justify-center text-sm font-medium transition-colors ${currentQuestionIndex === idx
                                ? 'bg-indigo-600 text-white'
                                : answers[q.id]
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-gray-200 text-gray-600'
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
