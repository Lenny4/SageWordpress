import React, { FC } from "react";

export interface FormInterface {
  content: FormContentInterface[];
}

export interface FormContentInterface {
  Container?: any;
  props?: any;
  Dom?: React.ReactNode;
  fields?: FieldInterface[];
  children?: FormContentInterface[];
}

export interface InputInterface {
  value: any;
  error?: string | null;
  readOnly?: boolean;
}

export interface FieldInterface {
  name: string;
  DomField: FC<FormInputProps>;
  readOnly?: boolean;
  hideLabel?: boolean;
  options?: FormInputOptions[];
  type?: string;
}

export type FormInputProps = {
  label: string;
  name: string;
  value: string;
  readOnly?: boolean;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onChangeSelect?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  hideLabel?: boolean;
  options?: FormInputOptions[];
  type?: string;
};

export type FormInputOptions = {
  label: string;
  value: string;
  disabled?: boolean;
};
