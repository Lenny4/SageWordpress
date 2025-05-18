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
}

export interface FieldInterface {
  name: string;
  DomField: FC<FormInputProps>;
  readOnly?: boolean;
}

export type FormInputProps = {
  label: string;
  name: string;
  value: string;
  readOnly?: boolean;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
};
