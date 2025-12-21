import React, { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowUpTrayIcon, DocumentTextIcon, ChartBarIcon, UserGroupIcon, CloudArrowUpIcon, DocumentIcon, AcademicCapIcon, PlusIcon } from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';

const QuizManage = () => {
    const { quizId } = useParams();
    const [quiz, setQuiz] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [waitingStudents, setWaitingStudents] = useState([]);
    const [invalidEntries, setInvalidEntries] = useState([]);

    // Add Question State
    const [showAddForm, setShowAddForm] = useState(false);
    const [newQuestion, setNewQuestion] = useState({
        question_text: '',
        question_type: 'multiple_choice',
        points: 1,
        options: [
            { id: 1, option_text: '', is_correct: false },
            { id: 2, option_text: '', is_correct: false },
            { id: 3, option_text: '', is_correct: false },
            { id: 4, option_text: '', is_correct: false }
        ]
    });
    const [addingQuestion, setAddingQuestion] = useState(false);

    // Material Upload State
    const [materialFile, setMaterialFile] = useState(null);
    const [uploadingMaterial, setUploadingMaterial] = useState(false);
    const [materialStatus, setMaterialStatus] = useState('');

    const [classes, setClasses] = useState([]);
    const [selectedClasses, setSelectedClasses] = useState([]);

    const fetchQuiz = useCallback(async () => {
        try {
            const response = await teacherAPI.getQuiz(quizId);
            const data = response.data.data;
            setQuiz(data);
            setSelectedClasses(data.allowed_classes?.map(c => c.id) || []);
            setLoading(false);
        } catch (error) {
            setError(error.response?.data?.message || 'Failed to load quiz details.');
            setLoading(false);
        }
    }, [quizId]);

    const fetchClasses = useCallback(async () => {
        try {
            const res = await teacherAPI.getClasses();
            setClasses(res.data.data || []);
        } catch (error) {
            // Silently fail or handle gracefully
        }
    }, []);

    const handleClassToggle = useCallback(async (classId) => {
        const newSelected = selectedClasses.includes(classId)
            ? selectedClasses.filter(id => id !== classId)
            : [...selectedClasses, classId];

        setSelectedClasses(newSelected);
        try {
            await teacherAPI.setQuizClasses(quizId, newSelected);
        } catch (error) {
            alert('Failed to update class restrictions');
        }
    }, [quizId, selectedClasses]);

    const fetchWaitingRoom = useCallback(async () => {
        try {
            const res = await teacherAPI.getWaitingStudents(quizId);
            setWaitingStudents(res.data.data.participants || []);
            setInvalidEntries(res.data.data.invalid_entries || []);
        } catch (error) {
            console.error('Error fetching participants:', error);
        }
    }, [quizId]);

    const getStatusBadge = (status) => {
        switch (status) {
            case 'submitted':
                return <span className="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded-full border border-green-200 uppercase">Submitted</span>;
            case 'in_progress':
                return <span className="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded-full border border-blue-200 uppercase animate-pulse">In Progress</span>;
            case 'waiting':
                return <span className="px-2 py-0.5 bg-gray-100 text-gray-800 text-[10px] font-bold rounded-full border border-gray-200 uppercase">Waiting</span>;
            default:
                return null;
        }
    };

    const updateStatus = async (newStatus) => {
        try {
            await teacherAPI.updateQuizStatus(quizId, newStatus);
            fetchQuiz();
        } catch (error) {
            const msg = error.response?.data?.message || error.message;
            alert('Failed to update status: ' + msg);
        }
    };

    useEffect(() => {
        fetchQuiz();
        fetchClasses();
        const pollInterval = setInterval(fetchWaitingRoom, 5000);
        return () => clearInterval(pollInterval);
    }, [fetchQuiz, fetchClasses, fetchWaitingRoom]);

    const handleAddQuestion = async (e) => {
        e.preventDefault();
        if (!newQuestion.question_text) return;
        setAddingQuestion(true);

        try {
            let correctAnswer = '';
            if (newQuestion.question_type === 'short_answer') {
                correctAnswer = 'MANUAL_GRADING';
            } else {
                const correctOpt = newQuestion.options.find(o => o.is_correct);
                correctAnswer = correctOpt ? correctOpt.id : '';
            }

            await teacherAPI.addQuestion(quizId, {
                ...newQuestion,
                correct_answer: correctAnswer
            });

            setShowAddForm(false);
            setNewQuestion({
                question_text: '',
                question_type: 'multiple_choice',
                points: 1,
                options: [
                    { id: 1, option_text: '', is_correct: false },
                    { id: 2, option_text: '', is_correct: false },
                    { id: 3, option_text: '', is_correct: false },
                    { id: 4, option_text: '', is_correct: false }
                ]
            });
            fetchQuiz();
        } catch (error) {
            alert('Failed to add question: ' + (error.response?.data?.message || error.message));
        } finally {
            setAddingQuestion(false);
        }
    };

    const updateNewQuestionOption = (id, field, value) => {
        const newOptions = newQuestion.options.map(o =>
            o.id === id ? { ...o, [field]: value } : o
        );

        if (field === 'is_correct' && value === true) {
            setNewQuestion({
                ...newQuestion,
                options: newOptions.map(o =>
                    o.id === id ? o : { ...o, is_correct: false }
                )
            });
        } else {
            setNewQuestion({ ...newQuestion, options: newOptions });
        }
    };

    const handleTypeChange = (type) => {
        let options = [];
        if (type === 'multiple_choice') {
            options = [
                { id: 1, option_text: '', is_correct: false },
                { id: 2, option_text: '', is_correct: false },
                { id: 3, option_text: '', is_correct: false },
                { id: 4, option_text: '', is_correct: false }
            ];
        } else if (type === 'true_false') {
            options = [
                { id: 1, option_text: 'True', is_correct: true },
                { id: 2, option_text: 'False', is_correct: false }
            ];
        }
        setNewQuestion({ ...newQuestion, question_type: type, options });
    };

    const handleMaterialUpload = (e) => setMaterialFile(e.target.files[0]);

    const uploadMaterial = async () => {
        if (!materialFile) return;
        setUploadingMaterial(true);
        setMaterialStatus('Uploading...');

        const formData = new FormData();
        formData.append('material', materialFile);

        try {
            await teacherAPI.uploadMaterial(quizId, formData);
            setMaterialStatus('Material uploaded successfully!');
            fetchQuiz();
            setMaterialFile(null);
        } catch (error) {
            console.error('Material upload failed:', error);
            setMaterialStatus('Upload failed. Try a smaller file or different format.');
        } finally {
            setUploadingMaterial(false);
        }
    };

    if (loading) return (
        <div className="flex flex-col items-center justify-center h-64 space-y-4">
            <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            <p className="text-gray-500 animate-pulse font-medium">Loading quiz details...</p>
        </div>
    );

    if (error) return (
        <div className="max-w-2xl mx-auto mt-12 p-8 bg-red-50 border border-red-200 rounded-2xl text-center">
            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <AcademicCapIcon className="h-8 w-8 text-red-600" />
            </div>
            <h2 className="text-xl font-bold text-red-800 mb-2">Oops! Something went wrong</h2>
            <p className="text-red-600 mb-6">{error}</p>
            <div className="space-y-4">
                <button
                    onClick={() => { setError(null); setLoading(true); fetchQuiz(); fetchClasses(); }}
                    className="btn bg-red-600 hover:bg-red-700 text-white px-8 py-2 rounded-lg"
                >
                    Retry Loading
                </button>
                <div className="text-sm text-red-500 bg-white/50 p-4 rounded-lg border border-red-100">
                    <p className="font-bold mb-1">Teacher Tip:</p>
                    <p>If you just added the "Classes" feature, please make sure you ran the migration script by opening your terminal and typing:</p>
                    <code className="block mt-2 p-2 bg-gray-900 text-gray-100 rounded font-mono">php migrate_classes.php</code>
                </div>
            </div>
        </div>
    );

    if (!quiz) return <div className="text-center py-12">Quiz not found</div>;


    return (
        <div className="space-y-8 pb-12">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Manage Quiz: {quiz.title}</h1>
                <div className="flex space-x-3 items-center">
                    {quiz.status === 'draft' && (() => {
                        const hasQuestions = quiz.questions?.length > 0;
                        const hasClasses = selectedClasses.length > 0;
                        const isReady = hasQuestions && hasClasses;

                        return (
                            <div className="flex flex-col items-end">
                                {!isReady && (
                                    <span className="text-[10px] font-bold text-red-500 mb-1 uppercase">
                                        {!hasQuestions && !hasClasses ? "Add questions & select class" :
                                            !hasQuestions ? "Add questions first" : "Select a class first"}
                                    </span>
                                )}
                                <button
                                    onClick={() => updateStatus('active')}
                                    disabled={!isReady}
                                    className={`btn font-bold px-6 py-2 rounded-lg shadow-lg transition-all ${isReady
                                        ? 'bg-green-600 hover:bg-green-700 text-white'
                                        : 'bg-gray-200 text-gray-400 cursor-not-allowed border-gray-300'
                                        }`}
                                >
                                    ACTIVATE QUIZ
                                </button>
                            </div>
                        );
                    })()}
                    {quiz.status === 'active' && (() => {
                        const hasQuestions = quiz.questions?.length > 0;
                        const hasClasses = selectedClasses.length > 0;
                        const isReady = hasQuestions && hasClasses;

                        return (
                            <div className="flex flex-col items-end">
                                {!isReady && (
                                    <span className="text-[10px] font-bold text-red-500 mb-1 uppercase">
                                        Quiz setup incomplete
                                    </span>
                                )}
                                <button
                                    onClick={() => updateStatus('started')}
                                    disabled={!isReady}
                                    className={`btn font-bold px-8 py-2 rounded-lg shadow-lg transition-all ${isReady
                                        ? 'bg-indigo-600 hover:bg-indigo-700 text-white animate-bounce'
                                        : 'bg-gray-200 text-gray-400 cursor-not-allowed border-gray-300'
                                        }`}
                                >
                                    START QUIZ NOW
                                </button>
                            </div>
                        );
                    })()}
                    {quiz.status === 'started' && (
                        <div className="px-4 py-2 bg-green-100 text-green-800 rounded-lg font-bold border border-green-200">
                            QUIZ IN PROGRESS
                        </div>
                    )}
                    {quiz.status === 'finished' && (
                        <div className="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg font-bold border border-gray-200">
                            QUIZ FINISHED
                        </div>
                    )}
                    <Link to={`/teacher/quiz/${quizId}/results`} className="btn btn-primary flex items-center">
                        <ChartBarIcon className="h-5 w-5 mr-2" />
                        View Live Results
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column: Info & Uploads */}
                <div className="space-y-6 lg:col-span-1">
                    {/* Info Card */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 className="text-lg font-medium mb-4">Quiz Information</h2>
                        <div className="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <p className="flex justify-between">
                                <span className="font-bold">Room Code:</span>
                                <span className="font-mono text-xl text-indigo-600 bg-gray-100 px-2 py-1 rounded">{quiz.room_code}</span>
                            </p>
                            <p className="flex justify-between"><span>Status:</span> <span className="capitalize font-bold">{quiz.status}</span></p>
                            <p className="flex justify-between"><span>Duration:</span> <span>{quiz.duration_minutes} mins</span></p>
                            <p className="flex justify-between"><span>Total Points:</span> <span>{quiz.total_points}</span></p>

                            {quiz.status === 'draft' && (
                                <p className="text-xs text-orange-600 bg-orange-50 p-2 mt-2 rounded border border-orange-100">
                                    Quiz is currently a draft. Click <b>Activate</b> to allow students to join the Waiting Room.
                                </p>
                            )}

                            {quiz.status === 'active' && (
                                <p className="text-xs text-green-600 bg-green-50 p-2 mt-2 rounded border border-green-100">
                                    Quiz is <b>Active</b>. Students can now enter their ID and wait for you to start the exam.
                                </p>
                            )}

                            {quiz.material_url && (
                                <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <p className="font-bold mb-2">Study Material:</p>
                                    <a
                                        href={`http://localhost/online-quiz-exam-system/backend${quiz.material_url}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-indigo-600 hover:text-indigo-800 flex items-center"
                                    >
                                        <DocumentIcon className="h-5 w-5 mr-1" />
                                        View Material
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>


                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-2 border-indigo-200">
                        <div className="flex justify-between items-start mb-4">
                            <div>
                                <h2 className="text-lg font-bold flex items-center text-indigo-700">
                                    <div className="w-3 h-3 bg-red-500 rounded-full animate-pulse mr-2"></div>
                                    Waiting Room & Progress
                                </h2>
                                <p className="text-[10px] text-gray-500 font-bold uppercase mt-1">
                                    {waitingStudents.length} / {quiz.allowed_students_count || 0} Students Joined
                                </p>
                            </div>
                            <div className="flex flex-col items-end space-y-1 text-[10px] uppercase font-bold text-gray-400">
                                <span className="text-orange-500">Waiting: {waitingStudents.filter(s => s.status === 'waiting').length}</span>
                                <span className="text-blue-500">Doing: {waitingStudents.filter(s => s.status === 'in_progress').length}</span>
                                <span className="text-green-600">Done: {waitingStudents.filter(s => s.status === 'submitted').length}</span>
                            </div>
                        </div>
                        <div className="space-y-2 max-h-64 overflow-y-auto pr-2">
                            {waitingStudents.length > 0 ? (
                                waitingStudents.map((s, idx) => (
                                    <div key={idx} className="flex justify-between items-center text-sm p-2 bg-gray-50 dark:bg-gray-700 rounded border border-gray-100 dark:border-gray-600 transition-all hover:shadow-sm">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-gray-900 dark:text-gray-100">{s.name}</span>
                                            <span className="text-gray-500 text-[10px]">{s.student_id}</span>
                                        </div>
                                        {getStatusBadge(s.status)}
                                    </div>
                                ))
                            ) : (
                                <p className="text-center text-gray-500 text-sm italic py-4">Waiting for students to join...</p>
                            )}
                        </div>
                    </div>

                    {/* Invalid Entry Log */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 className="text-lg font-medium mb-4 text-red-600">Failed Entry Attempts</h2>
                        <div className="space-y-2 text-xs">
                            {invalidEntries.length > 0 ? (
                                invalidEntries.map((e, idx) => (
                                    <div key={idx} className="p-2 border-b border-gray-100 flex justify-between">
                                        <span className="font-mono">{e.student_id_attempt}</span>
                                        <span className="text-gray-400">{new Date(e.created_at).toLocaleTimeString()}</span>
                                    </div>
                                ))
                            ) : (
                                <p className="text-gray-400 italic">No failed attempts logged.</p>
                            )}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-2 border-green-100">
                        <h2 className="text-lg font-bold mb-4 flex items-center text-green-700">
                            <AcademicCapIcon className="h-5 w-5 mr-2" />
                            Class Access Control
                        </h2>
                        <div className="space-y-3">
                            <p className="text-xs text-gray-500 mb-2">Select which classes are allowed to enter this quiz.</p>
                            {classes.length > 0 ? (
                                classes.map((c) => (
                                    <label key={c.id} className="flex items-center p-3 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                            checked={selectedClasses.includes(c.id)}
                                            onChange={() => handleClassToggle(c.id)}
                                        />
                                        <div className="ml-3">
                                            <span className="text-sm font-bold block">{c.name}</span>
                                            <span className="text-[10px] text-gray-400 font-medium uppercase">{c.section || 'No Section'} • {c.academic_year}</span>
                                        </div>
                                    </label>
                                ))
                            ) : (
                                <div className="text-center py-4">
                                    <p className="text-xs text-gray-400 italic mb-3">No classes created yet.</p>
                                    <Link to="/teacher/classes" className="text-xs font-bold text-indigo-600 hover:underline">
                                        + Create My First Class
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Upload Material */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 className="text-lg font-medium mb-4 flex items-center">
                            <CloudArrowUpIcon className="h-5 w-5 mr-2" />
                            Upload Study Material
                        </h2>
                        <p className="text-xs text-gray-500 mb-2">PDF, DOCX, PPTX, Images</p>
                        <div className="space-y-4">
                            <input
                                type="file"
                                onChange={handleMaterialUpload}
                                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <button
                                onClick={uploadMaterial}
                                disabled={!materialFile || uploadingMaterial}
                                className={`w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 ${(!materialFile || uploadingMaterial) ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                <ArrowUpTrayIcon className="h-5 w-5 mr-2" />
                                {uploadingMaterial ? 'Uploading...' : 'Upload Material'}
                            </button>
                            {materialStatus && <p className="text-sm text-center text-gray-600">{materialStatus}</p>}
                        </div>
                    </div>
                </div>

                {/* Right Column: Questions & Students */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Questions List */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-medium flex items-center">
                                <DocumentTextIcon className="h-5 w-5 mr-2" />
                                Questions ({quiz.questions?.length || 0})
                            </h2>
                            <button
                                onClick={() => setShowAddForm(!showAddForm)}
                                className={`btn btn-sm flex items-center ${showAddForm ? 'bg-gray-100 text-gray-600' : 'bg-indigo-600 text-white'}`}
                            >
                                {showAddForm ? 'Cancel' : (
                                    <>
                                        <PlusIcon className="h-4 w-4 mr-1" />
                                        Add Question
                                    </>
                                )}
                            </button>
                        </div>

                        {showAddForm && (
                            <form onSubmit={handleAddQuestion} className="mb-8 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border-2 border-indigo-100 dark:border-indigo-900/50 space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                                        <select
                                            className="w-full p-2 text-sm border rounded-lg"
                                            value={newQuestion.question_type}
                                            onChange={(e) => handleTypeChange(e.target.value)}
                                        >
                                            <option value="multiple_choice">Multiple Choice</option>
                                            <option value="true_false">True / False</option>
                                            <option value="short_answer">Short Answer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Points</label>
                                        <input
                                            type="number"
                                            className="w-full p-2 text-sm border rounded-lg"
                                            min="1"
                                            value={newQuestion.points}
                                            onChange={(e) => setNewQuestion({ ...newQuestion, points: e.target.value })}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase mb-1">Question Text</label>
                                    <textarea
                                        className="w-full p-2 text-sm border rounded-lg"
                                        rows="2"
                                        placeholder="Enter question text..."
                                        required
                                        value={newQuestion.question_text}
                                        onChange={(e) => setNewQuestion({ ...newQuestion, question_text: e.target.value })}
                                    />
                                </div>

                                {newQuestion.question_type === 'multiple_choice' && (
                                    <div className="space-y-2">
                                        <label className="block text-xs font-bold text-gray-500 uppercase">Options (Select the correct one)</label>
                                        {newQuestion.options.map((opt, i) => (
                                            <div key={opt.id} className="flex items-center space-x-2">
                                                <input
                                                    type="radio"
                                                    name="new_correct"
                                                    checked={opt.is_correct}
                                                    onChange={() => updateNewQuestionOption(opt.id, 'is_correct', true)}
                                                />
                                                <input
                                                    type="text"
                                                    className="flex-1 p-2 text-sm border rounded-lg"
                                                    placeholder={`Option ${i + 1}`}
                                                    required
                                                    value={opt.option_text}
                                                    onChange={(e) => updateNewQuestionOption(opt.id, 'option_text', e.target.value)}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {newQuestion.question_type === 'true_false' && (
                                    <div className="flex space-x-4">
                                        {newQuestion.options.map(opt => (
                                            <label key={opt.id} className="flex items-center space-x-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="new_correct"
                                                    checked={opt.is_correct}
                                                    onChange={() => updateNewQuestionOption(opt.id, 'is_correct', true)}
                                                />
                                                <span className="text-sm">{opt.option_text}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}

                                <button
                                    type="submit"
                                    disabled={addingQuestion}
                                    className="w-full btn bg-indigo-600 text-white font-bold py-2 rounded-lg"
                                >
                                    {addingQuestion ? 'Adding...' : 'Save Question'}
                                </button>
                            </form>
                        )}
                        <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
                            {quiz.questions && quiz.questions.length > 0 ? (
                                quiz.questions.map((q, index) => (
                                    <div key={q.id} className="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <div className="flex justify-between items-start mb-2">
                                            <span className="font-medium text-indigo-600">Question {index + 1}</span>
                                            <span className="text-xs font-semibold px-2 py-1 bg-gray-100 rounded text-gray-600 uppercase">{q.question_type.replace('_', ' ')}</span>
                                        </div>
                                        <p className="text-gray-900 dark:text-white mb-2">{q.question_text}</p>
                                        <div className="text-xs text-gray-500 flex justify-between">
                                            <span>Points: {q.points}</span>
                                            {/* Show options preview if needed */}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-gray-500 italic text-center py-4">No questions added yet.</p>
                            )}
                        </div>
                    </div>

                    {/* Allowed Students List */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 className="text-lg font-medium mb-4 flex items-center">
                            <UserGroupIcon className="h-5 w-5 mr-2" />
                            Allowed Students ({quiz.allowed_students_count || 0})
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    {quiz.allowed_students && quiz.allowed_students.length > 0 ? (
                                        quiz.allowed_students.map((student) => (
                                            <tr key={student.id}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{student.name}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{student.student_id}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="2" className="px-6 py-4 text-center text-sm text-gray-500">No students allowed yet. Upload a roster.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default QuizManage;
