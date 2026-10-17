import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

export default function Judging({
  form,
  event,
  rubrics = [],
  sheets = [],
  connected = false,
  connection = null,
  submissionCount = 0,
}) {
  // Rubric state
  const [selectedRubricId, setSelectedRubricId] = useState(
    rubrics.length > 0 ? rubrics[0].id : null
  );
  const [rubricName, setRubricName] = useState(
    rubrics.length > 0 ? rubrics[0].name : 'Cosplay Competition Rubric'
  );
  const [rubricDesc, setRubricDesc] = useState(
    rubrics.length > 0 ? rubrics[0].description || '' : ''
  );
  const [criteria, setCriteria] = useState(
    rubrics.length > 0 && rubrics[0].criteria?.length > 0
      ? rubrics[0].criteria.map((c) => ({ name: c.name, max_points: c.max_points }))
      : [
          { name: 'Craftsmanship & Accuracy', max_points: 35 },
          { name: 'Stage Presence & Performance', max_points: 30 },
          { name: 'Prop Quality & Detailing', max_points: 20 },
          { name: 'Complexity & Needlework', max_points: 15 },
        ]
  );
  const [savingRubric, setSavingRubric] = useState(false);

  // Sheet generator state
  const [judgeInputs, setJudgeInputs] = useState(['Judge 1', 'Judge 2', 'Judge 3']);
  const [newJudgeName, setNewJudgeName] = useState('');
  const [scoreMode, setScoreMode] = useState('average');
  const [exportTarget, setExportTarget] = useState('google');
  const [generating, setGenerating] = useState(false);

  // Criteria handlers
  const handleAddCriterion = () => {
    setCriteria([...criteria, { name: '', max_points: 20 }]);
  };

  const handleRemoveCriterion = (index) => {
    setCriteria(criteria.filter((_, i) => i !== index));
  };

  const handleCriterionChange = (index, field, value) => {
    const updated = [...criteria];
    updated[index][field] = field === 'max_points' ? parseInt(value) || 0 : value;
    setCriteria(updated);
  };

  const totalPoints = criteria.reduce((sum, c) => sum + (c.max_points || 0), 0);

  const handleSaveRubric = (e) => {
    e.preventDefault();
    setSavingRubric(true);
    router.post(
      `/admin/forms/${form.id}/judging/rubrics`,
      {
        rubric_id: selectedRubricId,
        name: rubricName,
        description: rubricDesc,
        criteria,
      },
      {
        onFinish: () => setSavingRubric(false),
      }
    );
  };

  // Judge list handlers
  const handleAddJudge = () => {
    if (!newJudgeName.trim()) return;
    setJudgeInputs([...judgeInputs, newJudgeName.trim()]);
    setNewJudgeName('');
  };

  const handleRemoveJudge = (index) => {
    setJudgeInputs(judgeInputs.filter((_, i) => i !== index));
  };

  const handleGenerateSheet = (e) => {
    e.preventDefault();
    if (!selectedRubricId && rubrics.length === 0) {
      alert('Please save a scoring rubric first.');
      return;
    }
    if (judgeInputs.length === 0) {
      alert('Please add at least one judge name.');
      return;
    }

    setGenerating(true);
    router.post(
      `/admin/forms/${form.id}/judging/generate`,
      {
        judging_rubric_id: selectedRubricId || rubrics[0]?.id,
        judge_names: judgeInputs,
        score_mode: scoreMode,
        export_target: exportTarget,
      },
      {
        onFinish: () => setGenerating(false),
      }
    );
  };

  return (
    <AdminLayout>
      <Head title={`Judge Sheets: ${form.name}`} />
      <div className="page-content" style={{ maxWidth: 1200, margin: '0 auto' }}>
        {/* Page Header */}
        <div className="page-header flex justify-between items-center mb-4">
          <div>
            <p className="text-sm text-muted mb-1">
              <Link href="/admin/events">Events</Link> /{' '}
              <Link href={`/admin/events/${event.id}`}>{event.name}</Link> /{' '}
              <Link href={`/admin/forms/${form.id}/submissions`}>{form.name}</Link> / Judging Sheets
            </p>
            <h1 className="page-title" style={{ margin: 0 }}>
              Judge Google Sheet Generator
            </h1>
            <p className="page-subtitle" style={{ margin: '0.25rem 0 0' }}>
              Create ready-to-use multi-tab judging sheets with contestant photos, per-judge tabs, and live results leaderboard.
            </p>
          </div>

          <div className="flex gap-2">
            <Link href={`/admin/forms/${form.id}/submissions`}>
              <button className="btn btn-secondary btn-sm">Submissions ({submissionCount})</button>
            </Link>
            <Link href={`/admin/events/${event.id}/forms/${form.id}/builder`}>
              <button className="btn btn-ghost btn-sm">Builder</button>
            </Link>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem', marginBottom: '2rem' }}>
          {/* Section A: Rubric Builder */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div className="flex justify-between items-center mb-3">
              <div>
                <h2 style={{ fontSize: '1.15rem', fontWeight: 600, margin: 0 }}>
                  1. Scoring Rubric
                </h2>
                <p className="text-muted text-xs" style={{ margin: '0.2rem 0 0' }}>
                  Define the criteria and maximum points for evaluation.
                </p>
              </div>
              <span
                className="badge badge-primary"
                style={{ fontSize: '0.85rem', fontWeight: 700 }}
              >
                Total: {totalPoints} pts
              </span>
            </div>

            <form onSubmit={handleSaveRubric}>
              <div className="form-group mb-3">
                <label className="form-label text-xs">Rubric Name</label>
                <input
                  type="text"
                  className="form-control"
                  value={rubricName}
                  onChange={(e) => setRubricName(e.target.value)}
                  required
                />
              </div>

              <div className="form-group mb-4">
                <label className="form-label text-xs">Description (Optional)</label>
                <input
                  type="text"
                  className="form-control text-sm"
                  placeholder="e.g. EgyCon Cosplay Championship official scoring"
                  value={rubricDesc}
                  onChange={(e) => setRubricDesc(e.target.value)}
                />
              </div>

              <h4 className="text-xs uppercase font-bold text-muted mb-2">Evaluation Criteria</h4>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '1rem' }}>
                {criteria.map((crit, idx) => (
                  <div key={idx} className="flex items-center gap-2">
                    <input
                      type="text"
                      className="form-control text-sm"
                      style={{ flex: 1 }}
                      placeholder="Criterion name"
                      value={crit.name}
                      onChange={(e) => handleCriterionChange(idx, 'name', e.target.value)}
                      required
                    />
                    <input
                      type="number"
                      className="form-control text-sm"
                      style={{ width: 85 }}
                      min="1"
                      max="200"
                      value={crit.max_points}
                      onChange={(e) => handleCriterionChange(idx, 'max_points', e.target.value)}
                      required
                      title="Max Points"
                    />
                    <span className="text-xs text-muted">pts</span>
                    {criteria.length > 1 && (
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm text-danger"
                        onClick={() => handleRemoveCriterion(idx)}
                        title="Remove criterion"
                      >
                        ✕
                      </button>
                    )}
                  </div>
                ))}
              </div>

              <div className="flex justify-between items-center pt-3 border-top">
                <button
                  type="button"
                  className="btn btn-ghost btn-sm"
                  onClick={handleAddCriterion}
                >
                  + Add Criterion
                </button>
                <button
                  type="submit"
                  className="btn btn-primary btn-sm"
                  disabled={savingRubric}
                >
                  {savingRubric ? 'Saving...' : 'Save Rubric'}
                </button>
              </div>
            </form>
          </div>

          {/* Section B: Sheet Generator */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <h2 style={{ fontSize: '1.15rem', fontWeight: 600, margin: '0 0 0.25rem 0' }}>
              2. Generator Settings
            </h2>
            <p className="text-muted text-xs mb-3">
              Configure judge tabs and output format for the event.
            </p>

            <form onSubmit={handleGenerateSheet}>
              {/* Judge Names */}
              <div className="form-group mb-3">
                <label className="form-label text-xs">Judges Panel (One tab per judge)</label>
                <div className="flex gap-2 mb-2">
                  <input
                    type="text"
                    className="form-control text-sm"
                    placeholder="Enter judge name (e.g. Ahmed, Sara)"
                    value={newJudgeName}
                    onChange={(e) => setNewJudgeName(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        handleAddJudge();
                      }
                    }}
                  />
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    onClick={handleAddJudge}
                  >
                    Add
                  </button>
                </div>

                <div className="flex flex-wrap gap-1">
                  {judgeInputs.map((j, i) => (
                    <span
                      key={i}
                      className="badge badge-secondary"
                      style={{
                        padding: '0.35rem 0.6rem',
                        fontSize: '0.8rem',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '0.35rem',
                      }}
                    >
                      👤 {j}
                      <span
                        style={{ cursor: 'pointer', opacity: 0.7 }}
                        onClick={() => handleRemoveJudge(i)}
                      >
                        ✕
                      </span>
                    </span>
                  ))}
                </div>
              </div>

              {/* Scoring Mode */}
              <div className="form-group mb-3">
                <label className="form-label text-xs">Leaderboard Final Calculation</label>
                <div className="flex gap-4">
                  <label className="flex items-center gap-1 text-sm cursor-pointer">
                    <input
                      type="radio"
                      name="scoreMode"
                      value="average"
                      checked={scoreMode === 'average'}
                      onChange={(e) => setScoreMode(e.target.value)}
                    />
                    Average across judges (Standard)
                  </label>
                  <label className="flex items-center gap-1 text-sm cursor-pointer">
                    <input
                      type="radio"
                      name="scoreMode"
                      value="sum"
                      checked={scoreMode === 'sum'}
                      onChange={(e) => setScoreMode(e.target.value)}
                    />
                    Sum total of all judges
                  </label>
                </div>
              </div>

              {/* Export Target */}
              <div className="form-group mb-4">
                <label className="form-label text-xs">Destination</label>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  <label
                    className="flex items-center gap-2 p-2 rounded cursor-pointer"
                    style={{
                      border: exportTarget === 'google' ? '2px solid var(--color-primary)' : '1px solid var(--color-border)',
                      background: exportTarget === 'google' ? 'rgba(79, 70, 229, 0.05)' : 'transparent',
                    }}
                  >
                    <input
                      type="radio"
                      name="exportTarget"
                      value="google"
                      checked={exportTarget === 'google'}
                      onChange={(e) => setExportTarget(e.target.value)}
                    />
                    <div>
                      <span className="text-sm font-semibold block">
                        📊 Live Google Sheet (Cloud)
                      </span>
                      <span className="text-xs text-muted">
                        Creates an editable Google Spreadsheet on your Google Drive with live sync.
                        {connected ? ` Connected as ${connection?.email}` : ' (Requires Google Connection)'}
                      </span>
                    </div>
                  </label>

                  <label
                    className="flex items-center gap-2 p-2 rounded cursor-pointer"
                    style={{
                      border: exportTarget === 'offline' ? '2px solid var(--color-primary)' : '1px solid var(--color-border)',
                      background: exportTarget === 'offline' ? 'rgba(79, 70, 229, 0.05)' : 'transparent',
                    }}
                  >
                    <input
                      type="radio"
                      name="exportTarget"
                      value="offline"
                      checked={exportTarget === 'offline'}
                      onChange={(e) => setExportTarget(e.target.value)}
                    />
                    <div>
                      <span className="text-sm font-semibold block">
                        💾 Offline Excel Workbook (.xlsx)
                      </span>
                      <span className="text-xs text-muted">
                        Instant download for offline judge laptops or tablets with pre-configured formulas.
                      </span>
                    </div>
                  </label>
                </div>
              </div>

              <button
                type="submit"
                className="btn btn-primary"
                style={{ width: '100%', padding: '0.75rem', fontWeight: 600 }}
                disabled={generating}
              >
                {generating
                  ? 'Generating Sheet...'
                  : exportTarget === 'google'
                  ? '🚀 Generate Live Google Sheet'
                  : '📥 Download Judging Spreadsheet (.xlsx)'}
              </button>
            </form>
          </div>
        </div>

        {/* Section C: Generated Sheets History */}
        <div className="card" style={{ padding: '1.5rem' }}>
          <h2 style={{ fontSize: '1.15rem', fontWeight: 600, margin: '0 0 0.5rem 0' }}>
            Generated Judging Sheets History
          </h2>

          {sheets.length === 0 ? (
            <p className="text-muted text-sm" style={{ margin: '0.5rem 0' }}>
              No judging sheets have been generated yet for this form. Use the generator above to create one.
            </p>
          ) : (
            <div className="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Rubric</th>
                    <th>Judges Panel</th>
                    <th>Created At</th>
                    <th style={{ width: 140 }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {sheets.map((sheet) => (
                    <tr key={sheet.id}>
                      <td style={{ fontWeight: 600 }}>{sheet.title}</td>
                      <td>{sheet.rubric?.name || 'Standard'}</td>
                      <td>
                        <span className="badge badge-secondary" style={{ fontSize: '0.75rem' }}>
                          {sheet.judge_names?.length || 0} Judges ({sheet.judge_names?.join(', ')})
                        </span>
                      </td>
                      <td className="text-muted text-xs">
                        {new Date(sheet.created_at).toLocaleString()}
                      </td>
                      <td>
                        {sheet.spreadsheet_url ? (
                          <a
                            href={sheet.spreadsheet_url}
                            target="_blank"
                            rel="noreferrer"
                            className="btn btn-primary btn-sm"
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
                          >
                            Open Sheet ↗
                          </a>
                        ) : (
                          <span className="text-muted text-xs">— offline file —</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
