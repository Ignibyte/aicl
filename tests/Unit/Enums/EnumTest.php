<?php

namespace Aicl\Tests\Unit\Enums;

use Aicl\Enums\AnnotationCategory;
use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Enums\ResolutionMethod;
use Aicl\Enums\ScoreType;
use Tests\TestCase;

class EnumTest extends TestCase
{
    // ─── ResolutionMethod ───────────────────────────────────────────────

    public function test_resolution_method_case_count(): void
    {
        $this->assertCount(5, ResolutionMethod::cases());
    }

    public function test_resolution_method_has_valid_labels(): void
    {
        foreach (ResolutionMethod::cases() as $case) {
            $this->assertNotEmpty($case->label(), "{$case->name} should have a label");
        }
    }

    public function test_resolution_method_has_valid_colors(): void
    {
        foreach (ResolutionMethod::cases() as $case) {
            $this->assertNotEmpty($case->color(), "{$case->name} should have a color");
        }
    }

    public function test_resolution_method_specific_labels(): void
    {
        $this->assertSame('Scaffolding Fix', ResolutionMethod::ScaffoldingFix->label());
        $this->assertSame('Manual Fix', ResolutionMethod::ManualFix->label());
        $this->assertSame('Workaround', ResolutionMethod::Workaround->label());
        $this->assertSame("Won't Fix", ResolutionMethod::WontFix->label());
        $this->assertSame('Duplicate', ResolutionMethod::Duplicate->label());
    }

    public function test_resolution_method_specific_colors(): void
    {
        $this->assertSame('success', ResolutionMethod::ScaffoldingFix->color());
        $this->assertSame('info', ResolutionMethod::ManualFix->color());
        $this->assertSame('warning', ResolutionMethod::Workaround->color());
        $this->assertSame('danger', ResolutionMethod::WontFix->color());
        $this->assertSame('gray', ResolutionMethod::Duplicate->color());
    }

    // ─── FailureCategory ────────────────────────────────────────────────

    public function test_failure_category_case_count(): void
    {
        $this->assertCount(9, FailureCategory::cases());
    }

    public function test_failure_category_has_valid_labels(): void
    {
        foreach (FailureCategory::cases() as $case) {
            $this->assertNotEmpty($case->label(), "{$case->name} should have a label");
        }
    }

    public function test_failure_category_has_valid_colors(): void
    {
        foreach (FailureCategory::cases() as $case) {
            $this->assertNotEmpty($case->color(), "{$case->name} should have a color");
        }
    }

    public function test_failure_category_specific_labels(): void
    {
        $this->assertSame('Scaffolding', FailureCategory::Scaffolding->label());
        $this->assertSame('Process', FailureCategory::Process->label());
        $this->assertSame('Filament', FailureCategory::Filament->label());
        $this->assertSame('Testing', FailureCategory::Testing->label());
        $this->assertSame('Auth', FailureCategory::Auth->label());
        $this->assertSame('Laravel', FailureCategory::Laravel->label());
        $this->assertSame('Tailwind', FailureCategory::Tailwind->label());
        $this->assertSame('Configuration', FailureCategory::Configuration->label());
        $this->assertSame('Other', FailureCategory::Other->label());
    }

    public function test_failure_category_specific_colors(): void
    {
        $this->assertSame('primary', FailureCategory::Scaffolding->color());
        $this->assertSame('info', FailureCategory::Process->color());
        $this->assertSame('warning', FailureCategory::Filament->color());
        $this->assertSame('success', FailureCategory::Testing->color());
        $this->assertSame('danger', FailureCategory::Auth->color());
        $this->assertSame('primary', FailureCategory::Laravel->color());
        $this->assertSame('info', FailureCategory::Tailwind->color());
        $this->assertSame('gray', FailureCategory::Configuration->color());
        $this->assertSame('gray', FailureCategory::Other->color());
    }

    // ─── FailureSeverity ────────────────────────────────────────────────

    public function test_failure_severity_case_count(): void
    {
        $this->assertCount(5, FailureSeverity::cases());
    }

    public function test_failure_severity_has_valid_labels(): void
    {
        foreach (FailureSeverity::cases() as $case) {
            $this->assertNotEmpty($case->label(), "{$case->name} should have a label");
        }
    }

    public function test_failure_severity_has_valid_colors(): void
    {
        foreach (FailureSeverity::cases() as $case) {
            $this->assertNotEmpty($case->color(), "{$case->name} should have a color");
        }
    }

    public function test_failure_severity_specific_labels(): void
    {
        $this->assertSame('Critical', FailureSeverity::Critical->label());
        $this->assertSame('High', FailureSeverity::High->label());
        $this->assertSame('Medium', FailureSeverity::Medium->label());
        $this->assertSame('Low', FailureSeverity::Low->label());
        $this->assertSame('Informational', FailureSeverity::Informational->label());
    }

    public function test_failure_severity_specific_colors(): void
    {
        $this->assertSame('danger', FailureSeverity::Critical->color());
        $this->assertSame('danger', FailureSeverity::High->color());
        $this->assertSame('warning', FailureSeverity::Medium->color());
        $this->assertSame('info', FailureSeverity::Low->color());
        $this->assertSame('gray', FailureSeverity::Informational->color());
    }

    // ─── ScoreType ──────────────────────────────────────────────────────

    public function test_score_type_case_count(): void
    {
        $this->assertCount(3, ScoreType::cases());
    }

    public function test_score_type_has_valid_labels(): void
    {
        foreach (ScoreType::cases() as $case) {
            $this->assertNotEmpty($case->label(), "{$case->name} should have a label");
        }
    }

    public function test_score_type_has_valid_colors(): void
    {
        foreach (ScoreType::cases() as $case) {
            $this->assertNotEmpty($case->color(), "{$case->name} should have a color");
        }
    }

    public function test_score_type_specific_labels(): void
    {
        $this->assertSame('Structural', ScoreType::Structural->label());
        $this->assertSame('Semantic', ScoreType::Semantic->label());
        $this->assertSame('Combined', ScoreType::Combined->label());
    }

    public function test_score_type_specific_colors(): void
    {
        $this->assertSame('primary', ScoreType::Structural->color());
        $this->assertSame('info', ScoreType::Semantic->color());
        $this->assertSame('success', ScoreType::Combined->color());
    }

    // ─── AnnotationCategory ─────────────────────────────────────────────

    public function test_annotation_category_case_count(): void
    {
        $this->assertCount(10, AnnotationCategory::cases());
    }

    public function test_annotation_category_has_valid_labels(): void
    {
        foreach (AnnotationCategory::cases() as $case) {
            $this->assertNotEmpty($case->label(), "{$case->name} should have a label");
        }
    }

    public function test_annotation_category_has_valid_colors(): void
    {
        foreach (AnnotationCategory::cases() as $case) {
            $this->assertNotEmpty($case->color(), "{$case->name} should have a color");
        }
    }

    public function test_annotation_category_specific_labels(): void
    {
        $this->assertSame('Model', AnnotationCategory::Model->label());
        $this->assertSame('Migration', AnnotationCategory::Migration->label());
        $this->assertSame('Factory', AnnotationCategory::Factory->label());
        $this->assertSame('Policy', AnnotationCategory::Policy->label());
        $this->assertSame('Observer', AnnotationCategory::Observer->label());
        $this->assertSame('Filament', AnnotationCategory::Filament->label());
        $this->assertSame('API', AnnotationCategory::Api->label());
        $this->assertSame('Test', AnnotationCategory::Test->label());
        $this->assertSame('Notification', AnnotationCategory::Notification->label());
        $this->assertSame('PDF', AnnotationCategory::Pdf->label());
    }

    public function test_annotation_category_specific_colors(): void
    {
        $this->assertSame('primary', AnnotationCategory::Model->color());
        $this->assertSame('info', AnnotationCategory::Migration->color());
        $this->assertSame('success', AnnotationCategory::Factory->color());
        $this->assertSame('warning', AnnotationCategory::Policy->color());
        $this->assertSame('danger', AnnotationCategory::Observer->color());
        $this->assertSame('primary', AnnotationCategory::Filament->color());
        $this->assertSame('info', AnnotationCategory::Api->color());
        $this->assertSame('success', AnnotationCategory::Test->color());
        $this->assertSame('warning', AnnotationCategory::Notification->color());
        $this->assertSame('gray', AnnotationCategory::Pdf->color());
    }

    // ─── KnowledgeLinkRelationship ──────────────────────────────────────

    public function test_knowledge_link_case_count(): void
    {
        $this->assertCount(5, KnowledgeLinkRelationship::cases());
    }

    public function test_knowledge_link_has_valid_labels(): void
    {
        foreach (KnowledgeLinkRelationship::cases() as $case) {
            $this->assertNotEmpty($case->label(), "{$case->name} should have a label");
        }
    }

    public function test_knowledge_link_specific_labels(): void
    {
        $this->assertSame('Violated By', KnowledgeLinkRelationship::ViolatedBy->label());
        $this->assertSame('Prevents', KnowledgeLinkRelationship::Prevents->label());
        $this->assertSame('Learned From', KnowledgeLinkRelationship::LearnedFrom->label());
        $this->assertSame('Derived From', KnowledgeLinkRelationship::DerivedFrom->label());
        $this->assertSame('Related To', KnowledgeLinkRelationship::RelatedTo->label());
    }

    public function test_knowledge_link_inverse_is_reciprocal(): void
    {
        foreach (KnowledgeLinkRelationship::cases() as $case) {
            $inverse = $case->inverse();
            $this->assertInstanceOf(KnowledgeLinkRelationship::class, $inverse);
        }
    }

    public function test_knowledge_link_specific_inverses(): void
    {
        $this->assertSame(KnowledgeLinkRelationship::Prevents, KnowledgeLinkRelationship::ViolatedBy->inverse());
        $this->assertSame(KnowledgeLinkRelationship::ViolatedBy, KnowledgeLinkRelationship::Prevents->inverse());
        $this->assertSame(KnowledgeLinkRelationship::DerivedFrom, KnowledgeLinkRelationship::LearnedFrom->inverse());
        $this->assertSame(KnowledgeLinkRelationship::LearnedFrom, KnowledgeLinkRelationship::DerivedFrom->inverse());
        $this->assertSame(KnowledgeLinkRelationship::RelatedTo, KnowledgeLinkRelationship::RelatedTo->inverse());
    }

    public function test_knowledge_link_double_inverse_returns_original(): void
    {
        foreach (KnowledgeLinkRelationship::cases() as $case) {
            $this->assertSame($case, $case->inverse()->inverse(), "{$case->name} double-inverse should return the original");
        }
    }
}
