import * as React from "react";
import {useImperativeHandle, useRef} from "react";
import {Tooltip} from "@mui/material";
import {getTranslations} from "../../../../../functions/translations";
import {FormValidInterface, TriggerFormContentChanged,} from "../../../../../interface/InputInterface";
import {TOKEN} from "../../../../../token";

let translations: any = getTranslations();

export type AfPrincipalState = {
  selectedCtNum: string;
  ctNum: string;
  onAfPrincipalChangedParent: TriggerFormContentChanged;
};

export const AfPrincipalInput = React.forwardRef(
  (
    {selectedCtNum, ctNum, onAfPrincipalChangedParent}: AfPrincipalState,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    const name = `_${TOKEN}_fArtfournisses[${ctNum}][afPrincipal]`;

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = true;
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    const checked = selectedCtNum === ctNum;
    return (
      <>
        <label htmlFor={name} style={{display: "none"}}>
          <Tooltip title={name} arrow placement="top">
            <span>{translations.words.supplierRef}</span>
          </Tooltip>
        </label>
        <div style={{display: "flex", alignItems: "center"}}>
          <input
            type="hidden"
            id={name}
            name={name}
            value={checked ? "1" : "0"}
          />
          <input
            type="checkbox"
            value="1"
            checked={checked}
            onChange={(e) => {
              if (e.target.checked) {
                onAfPrincipalChangedParent(name, ctNum);
              }
            }}
            ref={inputRef}
          />
        </div>
      </>
    );
  },
);
