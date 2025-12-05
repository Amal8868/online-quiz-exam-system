import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
    CheckIcon,
    TrashIcon
} from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';

const CreateQuiz = () => {
    const navigate = useNavigate();
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(false);
    const [quizData, setQuizData] = useState({
        title: '',
        description: '',
        class_id: '',
        time_limit: 30,
        start_time: '',
        end_time: '',
    });
    const [questions, setQuestions] = useState([]);

    useEffect(() => {
        fetchClasses();
    }, []);

    const fetchClasses = async () => {
        try {
            const response = await teacherAPI.getClasses();
            setClasses(response.data.data);
            if (response.data.data.length > 0) {
                setQuizData(prev => ({ ...prev, class_id: response.data.data[0].id }));
            }
        } catch (error) {
            console.error('Error fetching classes:', error);
        }
    };

    const addQuestion = (type) => {
        const newQuestion = {
            id: Date.now(),
            question_text: '',
            question_type: type,
            points: 1,
            options: type === 'mcq' ? [
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

                // If setting correct answer for MCQ/TF, unset others
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
                await teacherAPI.addQuestion(quizId, {
                    question_text: q.question_text,
                    question_type: q.question_type,
                    points: q.points,
                    options: q.options
                });
            }

            navigate('/teacher');
        } catch (error) {
            console.error('Error creating quiz:', error);
            alert('Failed to create quiz. Please check all fields.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-4xl mx-auto space-y-8 pb-12">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Create New Quiz</h1>
            </div>

            <form onSubmit={handleSubmit} className="space-y-8">
                {/* Quiz Details */}
                <div className="card space-y-4">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white">Quiz Details</h2>

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                            <input
                                type="text"
                                required
                                className="input mt-1"
                                value={quizData.title}
                                onChange={e => setQuizData({ ...quizData, title: e.target.value })}
                                placeholder="e.g. Midterm Exam"
                            />
                        </div>

                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea
                                className="input mt-1"
                                rows={3}
                                value={quizData.description}
                                onChange={e => setQuizData({ ...quizData, description: e.target.value })}
                                placeholder="Instructions for students..."
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Class</label>
                            <select
                                className="input mt-1"
                                value={quizData.class_id}
                                onChange={e => setQuizData({ ...quizData, class_id: e.target.value })}
                            >
                                {classes.map(cls => (
                                    <option key={cls.id} value={cls.id}>{cls.name} ({cls.section})</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Time Limit (minutes)</label>
                            <input
                                type="number"
                                required
                                min="1"
                                className="input mt-1"
                                value={quizData.time_limit}
                                onChange={e => setQuizData({ ...quizData, time_limit: e.target.value })}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Time (Optional)</label>
                            <input
                                type="datetime-local"
                                className="input mt-1"
                                value={quizData.start_time}
                                onChange={e => setQuizData({ ...quizData, start_time: e.target.value })}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">End Time (Optional)</label>
                            <input
                                type="datetime-local"
                                className="input mt-1"
                                value={quizData.end_time}
                                onChange={e => setQuizData({ ...quizData, end_time: e.target.value })}
                            />
                        </div>
                    </div>
                </div>

                {/* Questions */}
                <div className="space-y-4">
                    <div className="flex justify-between items-center">
                        <h2 className="text-lg font-medium text-gray-900 dark:text-white">Questions ({questions.length})</h2>
                        <div className="flex space-x-2">
                            <button type="button" onClick={() => addQuestion('mcq')} className="btn btn-outline text-xs">
                                + MCQ
                            </button>
                            <button type="button" onClick={() => addQuestion('true_false')} className="btn btn-outline text-xs">
                                + True/False
                            </button>
                            <button type="button" onClick={() => addQuestion('short_answer')} className="btn btn-outline text-xs">
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
                                className="card relative"
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
                                        <span className="text-sm font-medium text-primary-600 uppercase tracking-wider">
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
                                        className="input"
                                        rows={2}
                                        placeholder="Enter question text..."
                                        value={q.question_text}
                                        onChange={e => updateQuestion(q.id, 'question_text', e.target.value)}
                                    />

                                    {/* Options for MCQ */}
                                    {q.question_type === 'mcq' && (
                                        <div className="space-y-2 pl-4 border-l-2 border-gray-100 dark:border-gray-700">
                                            {q.options.map((opt, i) => (
                                                <div key={opt.id} className="flex items-center space-x-3">
                                                    <input
                                                        type="radio"
                                                        name={`correct_${q.id}`}
                                                        checked={opt.is_correct}
                                                        onChange={() => updateOption(q.id, opt.id, 'is_correct', true)}
                                                        className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300"
                                                    />
                                                    <input
                                                        type="text"
                                                        required
                                                        className="flex-1 input py-1 text-sm"
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
                                                        className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300"
                                                    />
                                                    <span className="text-gray-700 dark:text-gray-300">{opt.option_text}</span>
                                                </label>
                                            ))}
                                        </div>
                                    )}

                                    {/* Short Answer Preview */}
                                    {q.question_type === 'short_answer' && (
                                        <div className="pl-4 text-sm text-gray-500 italic">
                                            Students will type their answer in a text box.
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
                        className={`btn btn-primary w-full sm:w-auto ${loading ? 'opacity-70 cursor-not-allowed' : ''}`}
                    >
                        {loading ? 'Creating Quiz...' : 'Save & Publish Quiz'}
                    </button>
                </div>
            </form>
        </div>
    );
};

export default CreateQuiz;
