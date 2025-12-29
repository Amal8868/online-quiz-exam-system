import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { TrashIcon, DocumentPlusIcon } from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';
import AlertModal from '../../components/AlertModal';

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
    const [classes, setClasses] = useState([]);
    const [selectedClasses, setSelectedClasses] = useState([]);

    // Modal state
    const [modal, setModal] = useState({
        isOpen: false,
        title: '',
        message: '',
        type: 'error'
    });

    const showAlert = (title, message, type = 'error') => {
        setModal({
            isOpen: true,
            title,
            message,
            type
        });
    };

    useEffect(() => {
        const fetchClasses = async () => {
            try {
                const res = await teacherAPI.getClasses();
                setClasses(res.data.data || []);
            } catch (err) {
                console.error('Error fetching classes:', err);
            }
        };
        fetchClasses();
    }, []);

    const addQuestion = (type) => {
        const newQuestion = {
            id: Date.now(),
            question_text: '',
            question_type: type,
            points: 1,
            // Init options
            options: (type === 'multiple_choice' || type === 'multiple_selection') ? [
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

                if (field === 'is_correct' && value === true && q.question_type !== 'multiple_selection') {
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

    const addOption = (qId) => {
        setQuestions(questions.map(q => {
            if (q.id === qId) {
                const newId = q.options.length > 0 ? Math.max(...q.options.map(o => o.id)) + 1 : 1;
                return {
                    ...q,
                    options: [...q.options, { id: newId, option_text: '', is_correct: false }]
                };
            }
            return q;
        }));
    };

    const removeOption = (qId, oId) => {
        setQuestions(questions.map(q => {
            if (q.id === qId) {
                // Prevent removing if only 2 options left (MCQ/MSQ usually need at least 2)
                if (q.options.length <= 2) return q;
                return {
                    ...q,
                    options: q.options.filter(o => o.id !== oId)
                };
            }
            return q;
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (selectedClasses.length === 0) {
            showAlert('Missing Information', 'Please select at least one class before publishing the quiz.', 'error');
            return;
        }

        if (questions.length === 0) {
            showAlert('Missing Information', 'Please add at least one question before publishing the quiz.', 'error');
            return;
        }

        setLoading(true);

        try {
            // 1. Create Quiz
            const quizRes = await teacherAPI.createQuiz(quizData);
            const quizId = quizRes.data.data.id;

            // 2. Associate Classes
            if (selectedClasses.length > 0) {
                await teacherAPI.setQuizClasses(quizId, selectedClasses);
            }

            // 3. Add Questions
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
                } else if (q.question_type === 'multiple_selection') {
                    const correctOpts = q.options.filter(o => o.is_correct);
                    correctAnswer = correctOpts.map(o => o.id).sort().join(',');
                } else {
                    const correctOpt = q.options.find(o => o.is_correct);
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
            showAlert('Creation Error', 'Failed to create quiz. ' + (error.response?.data?.message || ''), 'error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-4xl mx-auto space-y-8 pb-12">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-4 mb-2">
                        <div className="p-2.5 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                            <DocumentPlusIcon className="h-10 w-10 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Create New Quiz</h1>
                    </div>
                    <p className="text-slate-500 font-semibold uppercase tracking-widest text-[11px] mt-2">
                        DESIGN AND PUBLISH YOUR ASSESSMENT
                    </p>
                </div>
            </div>

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
                                className="input mt-1"
                                value={quizData.title}
                                onChange={e => setQuizData({ ...quizData, title: e.target.value })}
                                placeholder="e.g. Final Exam"
                            />
                        </div>

                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea
                                className="mt-1 block w-full bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border dark:text-white dark:placeholder-gray-400"
                                rows={3}
                                value={quizData.description}
                                onChange={e => setQuizData({ ...quizData, description: e.target.value })}
                            />
                        </div>

                        <div className="col-span-2 sm:col-span-1">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration (Minutes)</label>
                            <input
                                type="number"
                                required
                                min="1"
                                className="input mt-1"
                                value={quizData.duration_minutes}
                                onChange={e => setQuizData({ ...quizData, duration_minutes: e.target.value })}
                            />
                        </div>

                        <div className="col-span-2 sm:col-span-1">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Assign to Classes</label>
                            <div className="flex flex-wrap gap-2 max-h-[120px] overflow-y-auto p-1">
                                {classes.map(cls => (
                                    <button
                                        key={cls.id}
                                        type="button"
                                        onClick={() => {
                                            if (selectedClasses.includes(cls.id)) {
                                                setSelectedClasses(selectedClasses.filter(id => id !== cls.id));
                                            } else {
                                                setSelectedClasses([...selectedClasses, cls.id]);
                                            }
                                        }}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all border ${selectedClasses.includes(cls.id)
                                            ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm'
                                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-500 hover:border-indigo-300'
                                            }`}
                                    >
                                        {cls.name}
                                    </button>
                                ))}
                            </div>
                            {classes.length === 0 && (
                                <p className="text-[10px] text-gray-400 italic">No classes found.</p>
                            )}
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
                            <button type="button" onClick={() => addQuestion('multiple_selection')} className="inline-flex items-center px-3 py-1.5 border border-indigo-600 shadow-sm text-xs font-medium rounded text-indigo-600 bg-white hover:bg-indigo-50">
                                + MSQ (Multiple Selection)
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
                                            <label className="text-sm text-gray-500 dark:text-gray-400">Points:</label>
                                            <input
                                                type="number"
                                                min="1"
                                                className="input w-24 px-3 py-1"
                                                value={q.points}
                                                onChange={e => updateQuestion(q.id, 'points', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <textarea
                                        required
                                        className="mt-1 block w-full bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border dark:text-white dark:placeholder-gray-400"
                                        rows={2}
                                        placeholder="Enter question text..."
                                        value={q.question_text}
                                        onChange={e => updateQuestion(q.id, 'question_text', e.target.value)}
                                    />

                                    {/* Options for MCQ */}
                                    {(q.question_type === 'multiple_choice' || q.question_type === 'multiple_selection') && (
                                        <div className="space-y-2 pl-4 border-l-2 border-gray-100 dark:border-gray-700">
                                            {q.question_type === 'multiple_selection' && (
                                                <p className="text-xs text-indigo-600 font-bold mb-2">Check all correct answers</p>
                                            )}
                                            {q.options.map((opt, i) => (
                                                <div key={opt.id} className="flex items-center space-x-3 group">
                                                    <input
                                                        type={q.question_type === 'multiple_selection' ? 'checkbox' : 'radio'}
                                                        name={`correct_${q.id}`}
                                                        checked={opt.is_correct}
                                                        onChange={(e) => {
                                                            const val = q.question_type === 'multiple_selection' ? e.target.checked : true;
                                                            updateOption(q.id, opt.id, 'is_correct', val);
                                                        }}
                                                        className={`h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 ${q.question_type === 'multiple_selection' ? 'rounded' : ''}`}
                                                    />
                                                    <input
                                                        type="text"
                                                        required
                                                        className="flex-1 px-3 py-1 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded text-sm dark:text-white dark:placeholder-gray-400"
                                                        placeholder={`Option ${i + 1}`}
                                                        value={opt.option_text}
                                                        onChange={e => updateOption(q.id, opt.id, 'option_text', e.target.value)}
                                                    />
                                                    {q.options.length > 2 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeOption(q.id, opt.id)}
                                                            className="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                            <button
                                                type="button"
                                                onClick={() => addOption(q.id)}
                                                className="mt-2 text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center"
                                            >
                                                <span className="mr-1">+</span> Add Option
                                            </button>
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
                        disabled={loading}
                        className={`w-full sm:w-auto flex justify-center py-2 px-8 border border-transparent rounded-xl shadow-lg text-sm font-bold uppercase tracking-wider text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all active:scale-95 ${loading ? 'opacity-50 cursor-not-allowed grayscale' : ''}`}
                    >
                        {loading ? 'Creating Quiz...' : 'Save & Publish Quiz'}
                    </button>
                </div>
            </form>

            <AlertModal
                isOpen={modal.isOpen}
                onClose={() => setModal({ ...modal, isOpen: false })}
                title={modal.title}
                message={modal.message}
                type={modal.type}
            />
        </div>
    );
};

export default CreateQuiz;
