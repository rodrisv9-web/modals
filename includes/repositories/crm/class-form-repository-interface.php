<?php

interface VA_CRM_Form_Repository_Interface {
    /**
     * Returns the active entry types available in the CRM.
     *
     * @return array
     */
    public function get_entry_types(): array;

    /**
     * Retrieves the form fields associated to a specific entry type.
     *
     * @param int $entry_type_id Entry type identifier.
     *
     * @return array
     */
    public function get_form_fields_by_entry_type( int $entry_type_id ): array;
}
