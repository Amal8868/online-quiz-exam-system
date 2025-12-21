import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { TrashIcon } from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';

const CreateQuiz = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [quizData, setQuizData] = useState({
        title: '',
        description: '',
        duration_minutes: 30,
        timer_type: 'exam', // 'exam' or 'question'
    });
    const [questions, setQuestions] = useState([]);

    const addQuestion = (type) => {
        const newQuestion = {
            id: Date.now(),
            question_text: '',
            question_type: type,
            points: 1,
            // Init options
            options: type === 'multiple_choice' ? [ // Changed 'mcq' to 'multiple_choice' to match DB ENUM if needed, or map it. DB uses 'multiple_choice'.
                { id: 1, option_text: '', is_correct: false },
                { id: 2, option_text: '', is_correct: false },
                { id: 3, option_text: '', is_correct: false },
                { id: 4, option_text: '', is_correct: false }
            ] : type === 'true_false' ? [
                { id: 1, option_text: 'True', is_correct: true },
                { id: 2, option_text: 'False', is_correct: false }
            ] : []
        };
        setQuestions([...questions, newQuestion]);
    };

    const updateQuestion = (id, field, value) => {
        setQuestions(questions.map(q =>
            q.id === id ? { ...q, [field]: value } : q
        ));
    };

    const updateOption = (qId, oId, field, value) => {
        setQuestions(questions.map(q => {
            if (q.id === qId) {
                const newOptions = q.options.map(o =>
                    o.id === oId ? { ...o, [field]: value } : o
                );

                if (field === 'is_correct' && value === true) {
                    return {
                        ...q,
                        options: newOptions.map(o =>
                            o.id === oId ? o : { ...o, is_correct: false }
                        )
                    };
                }

                return { ...q, options: newOptions };
            }
            return q;
        }));
    };

    const removeQuestion = (id) => {
        setQuestions(questions.filter(q => q.id !== id));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            // 1. Create Quiz
            const quizRes = await teacherAPI.createQuiz(quizData);
            const quizId = quizRes.data.data.id;

            // 2. Add Questions
            for (const q of questions) {
                // Determine correct answer string for DB
                let correctAnswer = '';
                if (q.question_type === 'short_answer') {
                    // Start answer logic if needed? 
                    // For now, maybe short answer doesn't have auto-grading correct answer required in UI?
                    // DB `correct_answer` is NOT NULL. So we need it.
                    // For short answer, maybe we add a field for it?
                    // The UI above didn't show "Correct Answer" input for Short Answer.
                    // Let's assume manual grading, so we can put "MANUAL" or ask user.
                    // Let's add a "Model Answer" logic if we want, or just empty string?
                    correctAnswer = 'MANUAL_GRADING';
                } else {
                    const correctOpt = q.options.find(o => o.is_correct);
                    // store ID or Text? logic in ExamPage checked ID or Text using OR.
                    // Let's store ID if simple numbers, but better to store Text or index?
                    // If we store ID 1..4, and frontend generates 1..4, it matches.
                    correctAnswer = correctOpt ? correctOpt.id : '';
                }

                await teacherAPI.addQuestion(quizId, {
                    question_text: q.question_text,
                    question_type: q.question_type,
                    points: q.points,
                    options: q.options,
                    correct_answer: correctAnswer
                });
            }

            navigate('/teacher');
        } catch (error) {
            console.error('Error creating quiz:', error);
            alert('Failed to create quiz. ' + (error.response?.data?.message || ''));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-4xl mx-auto space-y-8 pb-12">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Create New Quiz</h1>

            <form onSubmit={handleSubmit} className="space-y-8">
                {/* Quiz Details */}
                <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white">Quiz Details</h2>

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                            <input
                                type="text"
                                required
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border"
                                value={quizData.title}
                                onChange={e => setQuizData({ ...quizData, title: e.target.value })}
                                placeholder="e.g. Final Exam"
                            />
                        </div>

                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border"
                                rows={3}
                                value={quizData.description}
                                onChange={e => setQuizData({ ...quizData, description: e.target.value })}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration (Minutes)</label>
                            <input
                                type="number"
                                required
                                min="1"
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border"
                                value={quizData.duration_minutes}
                                onChange={e => setQuizData({ ...quizData, duration_minutes: e.target.value })}
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Timer Type</label>
                            <select
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border"
                                value={quizData.timer_type}
                                onChange={e => setQuizData({ ...quizData, timer_type: e.target.value })}
                            >
                                <option value="exam">Exam Timer</option>
                                {/* <option value="question">Per Question</option> Not fully implemented in backend yet, stick to exam */}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Questions */}
                <div className="space-y-4">
                    <div className="flex justify-between items-center">
                        <h2 className="text-lg font-medium text-gray-900 dark:text-white">Questions ({questions.length})</h2>
                        <div className="flex space-x-2">
                            <button type="button" onClick={() => addQuestion('multiple_choice')} className="inline-flex items-center px-3 py-1.5 border border-indigo-600 shadow-sm text-xs font-medium rounded text-indigo-600 bg-white hover:bg-indigo-50">
                                + MCQ
                            </button>
                            <button type="button" onClick={() => addQuestion('true_false')} className="inline-flex items-center px-3 py-1.5 border border-indigo-600 shadow-sm text-xs font-medium rounded text-indigo-600 bg-white hover:bg-indigo-50">
                                + True/False
                            </button>
                            <button type="button" onClick={() => addQuestion('short_answer')} className="inline-flex items-center px-3 py-1.5 border border-indigo-600 shadow-sm text-xs font-medium rounded text-indigo-600 bg-white hover:bg-indigo-50">
                                + Short Answer
                            </button>
                        </div>
                    </div>

                    <AnimatePresence>
                        {questions.map((q, index) => (
                            <motion.div
                                key={q.id}
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, height: 0 }}
                                className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 relative"
                            >
                                <button
                                    type="button"
                                    onClick={() => removeQuestion(q.id)}
                                    className="absolute top-4 right-4 text-gray-400 hover:text-red-500"
                                >
                                    <TrashIcon className="h-5 w-5" />
                                </button>

                                <div className="space-y-4 pr-8">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-indigo-600 uppercase tracking-wider">
                                            Question {index + 1} • {q.question_type.replace('_', ' ')}
                                        </span>
                                        <div className="flex items-center space-x-2">
                                            <label className="text-sm text-gray-500">Points:</label>
                                            <input
                                                type="number"
                                                min="1"
                                                className="w-16 px-2 py-1 text-sm border rounded"
                                                value={q.points}
                                                onChange={e => updateQuestion(q.id, 'points', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <textarea
                                        required
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border"
                                        rows={2}
                                        placeholder="Enter question text..."
                                        value={q.question_text}
                                        onChange={e => updateQuestion(q.id, 'question_text', e.target.value)}
                                    />

                                    {/* Options for MCQ */}
                                    {q.question_type === 'multiple_choice' && (
                                        <div className="space-y-2 pl-4 border-l-2 border-gray-100 dark:border-gray-700">
                                            {q.options.map((opt, i) => (
                                                <div key={opt.id} className="flex items-center space-x-3">
                                                    <input
                                                        type="radio"
                                                        name={`correct_${q.id}`}
                                                        checked={opt.is_correct}
                                                        onChange={() => updateOption(q.id, opt.id, 'is_correct', true)}
                                                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                    />
                                                    <input
                                                        type="text"
                                                        required
                                                        className="flex-1 px-3 py-1 border rounded text-sm"
                                                        placeholder={`Option ${i + 1}`}
                                                        value={opt.option_text}
                                                        onChange={e => updateOption(q.id, opt.id, 'option_text', e.target.value)}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Options for True/False */}
                                    {q.question_type === 'true_false' && (
                                        <div className="flex space-x-6 pl-4">
                                            {q.options.map(opt => (
                                                <label key={opt.id} className="flex items-center space-x-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name={`correct_${q.id}`}
                                                        checked={opt.is_correct}
                                                        onChange={() => updateOption(q.id, opt.id, 'is_correct', true)}
                                                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                    />
                                                    <span className="text-gray-700 dark:text-gray-300">{opt.option_text}</span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </motion.div>
                        ))}
                    </AnimatePresence>
                </div>

                <div className="flex justify-end pt-6">
                    <button
                        type="submit"
                        disabled={loading || questions.length === 0}
                        className={`w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ${loading ? 'opacity-70 cursor-not-allowed' : ''}`}
                    >
                        {loading ? 'Creating Quiz...' : 'Save & Publish Quiz'}
                    </button>
                </div>
            </form>
        </div>
    );
};

export default CreateQuiz;
